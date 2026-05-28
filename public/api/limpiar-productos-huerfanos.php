<?php
// ─────────────────────────────────────────────────────────────
// api/limpiar-productos-huerfanos.php
// ONE-SHOT: identifica productos Stripe que NO están referenciados
// por ningún `cursos.stripe_price_id` ni `packs.stripe_price_id` y los
// archiva (active=false).
//
// Si en el body se pasa {"key":"…","dry_run":true}, solo devuelve la
// lista sin archivar. Sin dry_run, archiva.
//
// Auth: BACKUP_SECRET.
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/db-config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}
$body = json_decode(file_get_contents('php://input'), true) ?? [];
if (!defined('BACKUP_SECRET') || !hash_equals(BACKUP_SECRET, (string)($body['key'] ?? ''))) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado']);
    exit;
}
$dryRun = !empty($body['dry_run']);

function stripeApi(string $method, string $endpoint, array $params = []): array {
    $ch = curl_init('https://api.stripe.com/v1' . $endpoint);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CUSTOMREQUEST  => $method,
    ];
    if (!empty($params)) $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
    curl_setopt_array($ch, $opts);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($res, true);
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException("Stripe {$method} {$endpoint}: " . ($data['error']['message'] ?? "HTTP {$code}"));
    }
    return $data;
}

try {
    $pdo = obtenerPDO();

    // 1. Precios activos en BD (cursos + packs) — dos queries separadas
    // (las tablas tienen collation distinta y UNION falla)
    $a = $pdo->query('SELECT stripe_price_id FROM cursos WHERE stripe_price_id IS NOT NULL')
             ->fetchAll(PDO::FETCH_COLUMN);
    $b = $pdo->query('SELECT stripe_price_id FROM packs WHERE stripe_price_id IS NOT NULL')
             ->fetchAll(PDO::FETCH_COLUMN);
    $pricesActivos = array_unique(array_filter(array_merge($a, $b)));

    // 2. Resolver cada price → product
    $productosOk = [];
    foreach ($pricesActivos as $pid) {
        try {
            $p = stripeApi('GET', '/prices/' . urlencode($pid));
            if (!empty($p['product'])) $productosOk[$p['product']] = true;
        } catch (Throwable $e) {
            // Price ya no existe en Stripe; lo saltamos
        }
    }

    // 3. Listar todos los productos activos en Stripe (con paginación)
    $todosProductos = [];
    $startingAfter  = null;
    do {
        $q = '?limit=100&active=true' . ($startingAfter ? '&starting_after=' . $startingAfter : '');
        $resp = stripeApi('GET', '/products' . $q);
        foreach ($resp['data'] as $p) $todosProductos[] = $p;
        $startingAfter = $resp['has_more'] ? end($resp['data'])['id'] : null;
    } while ($startingAfter);

    // 4. Identificar huérfanos
    $huerfanos = array_values(array_filter($todosProductos, function ($p) use ($productosOk) {
        return !isset($productosOk[$p['id']]);
    }));

    // 5. Archivar (si no es dry_run)
    $archivados = [];
    if (!$dryRun) {
        foreach ($huerfanos as $h) {
            try {
                stripeApi('POST', '/products/' . urlencode($h['id']), ['active' => 'false']);
                $archivados[] = $h['id'];
            } catch (Throwable $e) {
                error_log("limpiar-huerfanos: no se pudo archivar {$h['id']}: " . $e->getMessage());
            }
        }
    }

    echo json_encode([
        'ok'              => true,
        'dry_run'         => $dryRun,
        'prices_activos'  => count($pricesActivos),
        'productos_ok'    => array_keys($productosOk),
        'todos_activos'   => count($todosProductos),
        'huerfanos'       => array_map(fn($h) => ['id' => $h['id'], 'name' => $h['name']], $huerfanos),
        'archivados'      => $archivados,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('limpiar-huerfanos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
}
