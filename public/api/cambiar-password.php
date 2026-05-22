<?php
// ─────────────────────────────────────────────────────────────
// api/cambiar-password.php
// POST { usuario_id, password_nueva }  →  {ok, mensaje}
// Cambio propio: usuario autenticado == usuario_id
// Reset por admin: usuario autenticado con rol admin, usuario_id distinto
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
$pdo  = obtenerPDO();
$user = requireAuth($pdo);

$body     = json_decode(file_get_contents('php://input'), true);
$userId   = isset($body['usuario_id']) ? (int)$body['usuario_id'] : 0;
$nuevaPwd = trim($body['password_nueva'] ?? '');

if (!$userId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Falta usuario_id']);
    exit;
}

// Propio cambio o reset por admin
if ($userId !== (int)$user['id'] && $user['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'No tienes permiso para cambiar esta contraseña']);
    exit;
}

if (strlen($nuevaPwd) < 8) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'La contraseña debe tener al menos 8 caracteres.']);
    exit;
}

$actorId = (int)$user['id'];

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
