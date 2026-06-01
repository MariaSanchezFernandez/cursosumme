<?php
// ─────────────────────────────────────────────────────────────
// api/setup-live-prices.php
// ONE-SHOT: escribe en BD los `stripe_price_id` de PRODUCCIÓN (live)
// creados al activar la cuenta Stripe (test → live, 2026-06-01).
//
// Los productos/precios live se crearon previamente vía la API de Stripe
// con el importe BASE (precio_visible / 1.21), porque el tax_rate de IVA
// es EXCLUSIVE y suma el 21% encima en el Checkout.
//
// Hace un UPDATE quirúrgico SOLO de la columna `stripe_price_id` de cada
// curso/pack según el mapa fijo de abajo. Idempotente: si el valor ya
// coincide, no escribe y lo reporta como "sin cambios".
//
// Auth: BACKUP_SECRET por body JSON {key} (mismo patrón que
// migrar-precios-iva-exclusive.php y setup-paquetes-stripe.php).
//
// Uso (una sola vez tras desplegar):
//   curl -sS https://cursosumme.es/api/setup-live-prices.php \
//     -H 'Content-Type: application/json' \
//     -d '{"key":"<BACKUP_SECRET>"}'
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

// Mapa fijo de price_id live por curso/pack (id de BD → price_id Stripe live)
$CURSOS = [
    6  => 'price_1TdaKbClFjdCxTSQ3zYLwGvk', // Ten un temario de 10 (200€ base)
    14 => 'price_1TdaKcClFjdCxTSQGJXwtpJT', // Técnicas de preparación (100€)
    15 => 'price_1TdaKcClFjdCxTSQnsCM4nfB', // Resolución estratégica de Supuestos (250€)
    7  => 'price_1TdaKdClFjdCxTSQEQN2PFGC', // Crea tu programación — Aula ordinaria (400€)
    12 => 'price_1TdaKdClFjdCxTSQxaN9ms3a', // Crea tu programación — Aula específica (400€)
    9  => 'price_1TdaKeClFjdCxTSQzK8chOLi', // Crea unas SA — Aula ordinaria (450€)
    13 => 'price_1TdaKfClFjdCxTSQ658gMDvN', // Crea unas SA — Aula específica (450€)
];
$PACKS = [
    1  => 'price_1TdaKfClFjdCxTSQzTfWYEZF', // Paquete Aula Ordinaria (1000€)
    2  => 'price_1TdaKgClFjdCxTSQbBKN3DiT', // Paquete Aula Específica (1000€)
];

$log = [];
try {
    $pdo = obtenerPDO();

    $selCurso = $pdo->prepare('SELECT stripe_price_id FROM cursos WHERE id = ?');
    $updCurso = $pdo->prepare('UPDATE cursos SET stripe_price_id = ? WHERE id = ?');
    foreach ($CURSOS as $id => $priceId) {
        $selCurso->execute([$id]);
        $actual = $selCurso->fetchColumn();
        if ($actual === false) { $log[] = "curso id=$id NO EXISTE — saltado"; continue; }
        if ($actual === $priceId) { $log[] = "curso id=$id sin cambios ($priceId)"; continue; }
        $updCurso->execute([$priceId, $id]);
        $log[] = "curso id=$id: $actual → $priceId";
    }

    $selPack = $pdo->prepare('SELECT stripe_price_id FROM packs WHERE id = ?');
    $updPack = $pdo->prepare('UPDATE packs SET stripe_price_id = ? WHERE id = ?');
    foreach ($PACKS as $id => $priceId) {
        $selPack->execute([$id]);
        $actual = $selPack->fetchColumn();
        if ($actual === false) { $log[] = "pack id=$id NO EXISTE — saltado"; continue; }
        if ($actual === $priceId) { $log[] = "pack id=$id sin cambios ($priceId)"; continue; }
        $updPack->execute([$priceId, $id]);
        $log[] = "pack id=$id: $actual → $priceId";
    }

    $cursos = $pdo->query('SELECT id, titulo, precio, stripe_price_id FROM cursos WHERE precio IS NOT NULL ORDER BY id')->fetchAll();
    $packs  = $pdo->query('SELECT id, nombre, precio, stripe_price_id FROM packs ORDER BY id')->fetchAll();

    echo json_encode([
        'ok'     => true,
        'log'    => $log,
        'cursos' => $cursos,
        'packs'  => $packs,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('setup-live-prices: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage(), 'log' => $log]);
}
