<?php
// ─────────────────────────────────────────────────────────────
// api/cambiar-password.php
// POST { usuario_id, password_nueva }  →  {ok, mensaje}
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$body      = json_decode(file_get_contents('php://input'), true);
$userId    = isset($body['usuario_id']) ? (int)$body['usuario_id'] : 0;
$nuevaPwd  = trim($body['password_nueva'] ?? '');

if (!$userId || strlen($nuevaPwd) < 6) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'La contraseña debe tener al menos 6 caracteres.']);
    exit;
}

require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();

$hash = hash('sha256', $nuevaPwd);
$stmt = $pdo->prepare('UPDATE usuarios SET contrasena = :hash WHERE id = :id');
$stmt->execute([':hash' => $hash, ':id' => $userId]);

echo json_encode(['ok' => true, 'mensaje' => 'Contraseña actualizada correctamente.']);
