<?php
// ─────────────────────────────────────────────────────────────
// api/pago-status.php  —  Estado de una sesión Stripe Checkout
// GET ?session_id=cs_xxx  →  { ok, estado, email }
// Se usa desde /pago-ok para confirmar que el webhook procesó el pago
// antes de mostrar "Pago completado".
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/db-config.php';

$sessionId = trim($_GET['session_id'] ?? '');

// Stripe Checkout IDs siempre empiezan por cs_ y tienen al menos ~30 chars
if (!preg_match('/^cs_[A-Za-z0-9_]{20,}$/', $sessionId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'session_id inválido']);
    exit;
}

$pdo  = obtenerPDO();
$stmt = $pdo->prepare('SELECT estado, email FROM pagos WHERE stripe_session_id=:sid LIMIT 1');
$stmt->execute([':sid' => $sessionId]);
$row = $stmt->fetch();

if (!$row) {
    // Sesión no registrada (ni siquiera entró por checkout): devolvemos pendiente
    // para que el frontend siga sondeando (puede que el webhook llegue antes que el INSERT,
    // aunque el flujo normal es al revés).
    echo json_encode(['ok' => true, 'estado' => 'pendiente', 'email' => null]);
    exit;
}

echo json_encode([
    'ok'     => true,
    'estado' => $row['estado'],
    'email'  => $row['email'] ?: null,
]);
