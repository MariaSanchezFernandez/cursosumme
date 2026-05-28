<?php
// ─────────────────────────────────────────────────────────────
// api/migrar-precios-iva-exclusive.php
// ONE-SHOT: recrea los Stripe Prices al importe BASE (precio/1.21)
// para que el tax_rate exclusive de Stripe muestre el desglose correcto:
//   Subtotal (base) + IVA 21% = Total (lo que paga el cliente)
//
// Para cada curso y pack en BD:
//  - Lee `precio` (que es el importe bruto que ve la usuaria, ej. 484€)
//  - Calcula base = round(precio / 1.21, 2) en céntimos
//  - Crea un nuevo Stripe Price asociado al MISMO producto Stripe que ya
//    tenía (consultado vía el price antiguo). Si no hay price antiguo, salta.
//  - Actualiza `stripe_price_id` en BD
//  - El price viejo no se borra (Stripe lo archiva automáticamente al no usarse)
//
// Idempotente: si el unit_amount del price actual coincide con la base,
// no recrea nada.
//
// Auth: BACKUP_SECRET (mismo patrón que setup-paquetes-stripe.php).
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

// ── Helper Stripe API ────────────────────────────────────────
function stripeApi(string $method, string $endpoint, array $params = []): array {
    $ch = curl_init('https://api.stripe.com/v1' . $endpoint);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CUSTOMREQUEST  => $method,
    ];
    if (!empty($params)) {
        $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
    }
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

// ── Recrear price para un item dado ───────────────────────────
function recrearPriceBase(string $stripePriceIdActual, float $precioBruto, string $nombre): ?string {
    // 1. Calcular base en céntimos
    $baseEur     = round($precioBruto / 1.21, 2);
    $baseCents   = (int)round($baseEur * 100);

    // 2. Obtener el price actual para conocer el product_id
    $priceActual = stripeApi('GET', '/prices/' . urlencode($stripePriceIdActual));
    $productId   = $priceActual['product'] ?? '';
    $unitActual  = (int)($priceActual['unit_amount'] ?? 0);

    // 3. Si el unit_amount ya es la base, no hacer nada
    if ($unitActual === $baseCents) {
        return null; // ya migrado
    }

    if (!$productId) {
        throw new RuntimeException("Price {$stripePriceIdActual} sin product");
    }

    // 4. Crear nuevo price con el unit_amount base, mismo producto
    $nuevo = stripeApi('POST', '/prices', [
        'product'     => $productId,
        'unit_amount' => $baseCents,
        'currency'    => 'eur',
    ]);

    // 5. Desactivar (archivar) el price antiguo para que no quede confusión
    try {
        stripeApi('POST', '/prices/' . urlencode($stripePriceIdActual), ['active' => 'false']);
    } catch (Throwable $e) {
        // no es bloqueante
        error_log('migrar-precios: no se pudo archivar ' . $stripePriceIdActual . ': ' . $e->getMessage());
    }

    return $nuevo['id'];
}

// ── Ejecución ────────────────────────────────────────────────
$log = [];
try {
    $pdo = obtenerPDO();

    // Cursos
    $cursos = $pdo->query(
        'SELECT id, titulo, precio, stripe_price_id FROM cursos
         WHERE precio IS NOT NULL AND stripe_price_id IS NOT NULL ORDER BY id'
    )->fetchAll();

    foreach ($cursos as $c) {
        $nuevoId = recrearPriceBase($c['stripe_price_id'], (float)$c['precio'], $c['titulo']);
        if ($nuevoId === null) {
            $log[] = "curso id={$c['id']} ya migrado ({$c['stripe_price_id']})";
            continue;
        }
        $pdo->prepare('UPDATE cursos SET stripe_price_id = ? WHERE id = ?')
            ->execute([$nuevoId, $c['id']]);
        $base = round((float)$c['precio'] / 1.21, 2);
        $log[] = "curso id={$c['id']} ({$c['precio']}€ → base {$base}€) → {$nuevoId}";
    }

    // Packs
    $packs = $pdo->query(
        'SELECT id, nombre, precio, stripe_price_id FROM packs
         WHERE precio IS NOT NULL AND stripe_price_id IS NOT NULL ORDER BY id'
    )->fetchAll();

    foreach ($packs as $p) {
        $nuevoId = recrearPriceBase($p['stripe_price_id'], (float)$p['precio'], $p['nombre']);
        if ($nuevoId === null) {
            $log[] = "pack id={$p['id']} ya migrado ({$p['stripe_price_id']})";
            continue;
        }
        $pdo->prepare('UPDATE packs SET stripe_price_id = ? WHERE id = ?')
            ->execute([$nuevoId, $p['id']]);
        $base = round((float)$p['precio'] / 1.21, 2);
        $log[] = "pack id={$p['id']} ({$p['precio']}€ → base {$base}€) → {$nuevoId}";
    }

    // Resumen
    $resCursos = $pdo->query(
        'SELECT id, titulo, precio, stripe_price_id FROM cursos WHERE precio IS NOT NULL ORDER BY id'
    )->fetchAll();
    $resPacks  = $pdo->query('SELECT id, nombre, precio, stripe_price_id FROM packs ORDER BY id')->fetchAll();

    echo json_encode([
        'ok'     => true,
        'log'    => $log,
        'cursos' => $resCursos,
        'packs'  => $resPacks,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('migrar-precios-iva-exclusive: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage(), 'log' => $log]);
}
