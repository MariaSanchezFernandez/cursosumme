<?php
// ─────────────────────────────────────────────────────────────
// api/cambiar-password.php
// POST { usuario_id, password_nueva, actor_id? }  →  {ok, mensaje}
// Si actor_id != usuario_id → reset por admin (queda en logs).
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
$actorId   = isset($body['actor_id'])   ? (int)$body['actor_id']   : $userId;
$nuevaPwd  = trim($body['password_nueva'] ?? '');

if (!$userId || strlen($nuevaPwd) < 6) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'La contraseña debe tener al menos 6 caracteres.']);
    exit;
}

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
$pdo = obtenerPDO();

$hash = password_hash($nuevaPwd, PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $pdo->prepare('UPDATE usuarios SET contrasena = :hash WHERE id = :id');
$stmt->execute([':hash' => $hash, ':id' => $userId]);

// Log: reset por admin vs cambio propio
$tipo = ($actorId !== $userId) ? 'password_reseteada' : 'password_cambiada';
$stmtNombre = $pdo->prepare('SELECT nombre, apellidos, email FROM usuarios WHERE id = :id');
$stmtNombre->execute([':id' => $userId]);
$u = $stmtNombre->fetch();
if (!$u) {
    $descripcion = "Contraseña actualizada (usuario ID {$userId})";
} elseif ($tipo === 'password_reseteada') {
    $descripcion = "Contraseña reseteada para {$u['nombre']} {$u['apellidos']} ({$u['email']})";
} else {
    $descripcion = "Contraseña cambiada por {$u['email']}";
}
registrar_log($pdo, $tipo, $descripcion, $actorId);

echo json_encode(['ok' => true, 'mensaje' => 'Contraseña actualizada correctamente.']);
