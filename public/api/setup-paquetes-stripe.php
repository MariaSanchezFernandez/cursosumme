<?php
// ─────────────────────────────────────────────────────────────
// api/setup-paquetes-stripe.php
// ENDPOINT DE ADMINISTRACIÓN — idempotente y protegido por BACKUP_SECRET.
//
// Hace en una sola operación:
//   1. CREATE TABLE IF NOT EXISTS pack_cursos
//   2. Activa el curso id=13 si está inactivo
//   3. Por cada curso de CURSOS, si no tiene stripe_price_id:
//      crea producto+precio en Stripe y lo guarda con `precio`
//   4. Por cada pack de PACKS, si no existe en BD: lo crea.
//      Si no tiene stripe_price_id: crea producto+precio en Stripe
//      y lo guarda.
//   5. INSERT IGNORE en pack_cursos para enlazar cursos ↔ packs
//
// Llamada:
//   curl -X POST https://cursosumme.es/api/setup-paquetes-stripe.php \
//        -H 'Content-Type: application/json' \
//        -d '{"key":"<BACKUP_SECRET>"}'
//
// Devuelve JSON con resumen + logs por línea.
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/db-config.php';

// ── Auth ─────────────────────────────────────────────────────
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

// ── Definiciones ─────────────────────────────────────────────
$CURSOS = [
    ['id' =>  6, 'precio' => 242.00,  'nombre' => 'Ten un temario de 10'],
    ['id' =>  7, 'precio' => 484.00,  'nombre' => 'Crea tu programación de 10 — Aula ordinaria'],
    ['id' =>  9, 'precio' => 544.50,  'nombre' => 'Crea Situaciones de Aprendizaje (SA) de 10 — Aula ordinaria'],
    ['id' => 12, 'precio' => 484.00,  'nombre' => 'Crea tu programación de 10 — Aula específica'],
    ['id' => 13, 'precio' => 544.50,  'nombre' => 'Crea Situaciones de Aprendizaje (SA) de 10 — Aula específica'],
    ['id' => 14, 'precio' => 121.00,  'nombre' => 'Técnicas de preparación'],
    ['id' => 15, 'precio' => 302.50,  'nombre' => 'Resolución estratégica de Supuestos Prácticos en Pedagogía Terapéutica'],
];

$PACKS = [
    [
        'nombre'      => 'Paquete Preparación Aula Ordinaria',
        'etiqueta'    => 'aula-ordinaria',
        'precio'      => 1210.00,
        'descripcion' => 'SA + Programación + Temario + Supuestos Prácticos para Aula Ordinaria. Incluye REGALO: curso de Técnicas de preparación.',
        'curso_ids'   => [9, 7, 6, 15, 14],
    ],
    [
        'nombre'      => 'Paquete Preparación Aula Específica',
        'etiqueta'    => 'aula-especifica',
        'precio'      => 1210.00,
        'descripcion' => 'SA + Programación + Temario + Supuestos Prácticos para Aula Específica. Incluye REGALO: curso de Técnicas de preparación.',
        'curso_ids'   => [13, 12, 6, 15, 14],
    ],
];

// ── Helper Stripe API ────────────────────────────────────────
function stripeApi(string $endpoint, array $params): array {
    $ch = curl_init('https://api.stripe.com/v1' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($res, true);
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException('Stripe ' . $endpoint . ': ' . ($data['error']['message'] ?? "HTTP {$code}"));
    }
    return $data;
}

function crearProductoYPrecio(string $nombre, float $precio): array {
    // El parámetro $precio es el importe BRUTO (con IVA inclu., ej. 484€).
    // Como usamos tax_rate EXCLUSIVE en checkout, el unit_amount de Stripe
    // tiene que ser la BASE imponible. Stripe sumará el 21% al cobrar y
    // el cliente paga exactamente $precio.
    $base = (int)round(($precio / 1.21) * 100);
    $prod  = stripeApi('/products', ['name' => $nombre]);
    $price = stripeApi('/prices', [
        'product'     => $prod['id'],
        'unit_amount' => $base,
        'currency'    => 'eur',
    ]);
    return ['product_id' => $prod['id'], 'price_id' => $price['id']];
}

// ── Ejecución ────────────────────────────────────────────────
$log = [];
try {
    $pdo = obtenerPDO();

    // 1. Migración
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS pack_cursos (
            pack_id     INT          NOT NULL,
            curso_id    INT UNSIGNED NOT NULL,
            asignado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (pack_id, curso_id),
            KEY idx_curso (curso_id),
            CONSTRAINT fk_pc_pack  FOREIGN KEY (pack_id)  REFERENCES packs(id)  ON DELETE CASCADE,
            CONSTRAINT fk_pc_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $log[] = 'pack_cursos creada (o ya existía)';

    $up = $pdo->prepare('UPDATE cursos SET activo = 1 WHERE id = 13 AND activo = 0');
    $up->execute();
    $log[] = 'curso id=13 activado (filas=' . $up->rowCount() . ')';

    // 2. Productos Stripe para cursos individuales
    foreach ($CURSOS as $c) {
        $sel = $pdo->prepare('SELECT id, titulo, precio, stripe_price_id FROM cursos WHERE id = :id LIMIT 1');
        $sel->execute([':id' => $c['id']]);
        $row = $sel->fetch();
        if (!$row) { $log[] = "curso id={$c['id']} no existe, salto"; continue; }
        if (!empty($row['stripe_price_id']) && !empty($row['precio'])) {
            $log[] = "curso id={$c['id']} ya tiene {$row['stripe_price_id']}, salto";
            continue;
        }
        $res = crearProductoYPrecio($c['nombre'], $c['precio']);
        $pdo->prepare('UPDATE cursos SET precio = :p, stripe_price_id = :s WHERE id = :id')
            ->execute([':p' => $c['precio'], ':s' => $res['price_id'], ':id' => $c['id']]);
        $log[] = "curso id={$c['id']} → {$res['price_id']} ({$c['precio']}€)";
    }

    // 3. Packs
    foreach ($PACKS as $p) {
        $sel = $pdo->prepare('SELECT id, stripe_price_id FROM packs WHERE nombre = :n LIMIT 1');
        $sel->execute([':n' => $p['nombre']]);
        $packRow = $sel->fetch();

        if (!$packRow) {
            $ins = $pdo->prepare(
                'INSERT INTO packs (nombre, descripcion, precio, etiqueta, activo)
                 VALUES (:n, :d, :pr, :e, 1)'
            );
            $ins->execute([
                ':n'  => $p['nombre'],
                ':d'  => $p['descripcion'],
                ':pr' => $p['precio'],
                ':e'  => $p['etiqueta'],
            ]);
            $packId = (int)$pdo->lastInsertId();
            $log[] = "pack creado: {$p['nombre']} (id={$packId})";
        } else {
            $packId = (int)$packRow['id'];
            $log[] = "pack ya existe: {$p['nombre']} (id={$packId})";
        }

        if (empty($packRow['stripe_price_id'])) {
            $res = crearProductoYPrecio($p['nombre'], $p['precio']);
            $pdo->prepare('UPDATE packs SET stripe_price_id = :s, precio = :pr, descripcion = :d WHERE id = :id')
                ->execute([
                    ':s'  => $res['price_id'],
                    ':pr' => $p['precio'],
                    ':d'  => $p['descripcion'],
                    ':id' => $packId,
                ]);
            $log[] = "pack id={$packId} → {$res['price_id']} ({$p['precio']}€)";
        }

        // Enlaces pack_cursos
        $linkStmt = $pdo->prepare('INSERT IGNORE INTO pack_cursos (pack_id, curso_id) VALUES (:p, :c)');
        foreach ($p['curso_ids'] as $cid) {
            $linkStmt->execute([':p' => $packId, ':c' => $cid]);
        }
        $log[] = "pack id={$packId} → " . count($p['curso_ids']) . ' enlaces';
    }

    // 4. Resumen
    $cursosResumen = $pdo->query(
        'SELECT id, titulo, precio, stripe_price_id FROM cursos WHERE precio IS NOT NULL ORDER BY id'
    )->fetchAll();
    $packsResumen = $pdo->query('SELECT id, nombre, precio, stripe_price_id FROM packs ORDER BY id')->fetchAll();
    $enlaces = $pdo->query(
        'SELECT p.nombre AS pack, c.titulo AS curso
         FROM pack_cursos pc
         JOIN packs  p ON p.id = pc.pack_id
         JOIN cursos c ON c.id = pc.curso_id
         ORDER BY p.id, c.id'
    )->fetchAll();

    echo json_encode([
        'ok'      => true,
        'log'     => $log,
        'cursos'  => $cursosResumen,
        'packs'   => $packsResumen,
        'enlaces' => $enlaces,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('setup-paquetes-stripe: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage(), 'log' => $log]);
}
