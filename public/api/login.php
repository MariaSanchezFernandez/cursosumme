<?php
// ─────────────────────────────────────────────────────────────
// api/login.php  —  Endpoint de autenticación Cursos Umme
// Método: POST  |  Content-Type: application/json
// Body:   { "email": "...", "contrasena": "..." }
// Respuesta OK:  { "ok": true,  "rol": "admin"|"alumno" }
// Respuesta KO:  { "ok": false, "mensaje": "..." }
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

// Solo aceptamos POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

// Leer el cuerpo JSON
$body = json_decode(file_get_contents('php://input'), true);

if (!isset($body['email'], $body['contrasena'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Faltan campos']);
    exit;
}

$email     = trim(strtolower($body['email']));
$hashEnv   = hash('sha256', $body['contrasena']);

// ── Conexión a la base de datos ──────────────────────────────
require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();

// ── Buscar usuario activo por email ──────────────────────────
$stmt = $pdo->prepare(
    'SELECT id, rol, nombre, contrasena, fecha_baja FROM usuarios WHERE email = :email AND activo = 1 LIMIT 1'
);
$stmt->execute([':email' => $email]);
$usuario = $stmt->fetch();

if (!$usuario) {
    echo json_encode(['ok' => false, 'mensaje' => 'Credenciales incorrectas']);
    exit;
}

// ── Comparar hash ─────────────────────────────────────────────
if (!hash_equals($usuario['contrasena'], $hashEnv)) {
    echo json_encode(['ok' => false, 'mensaje' => 'Credenciales incorrectas']);
    exit;
}

// ── Comprobar fecha de baja ────────────────────────────────────
if (!empty($usuario['fecha_baja']) && $usuario['fecha_baja'] < date('Y-m-d')) {
    echo json_encode(['ok' => false, 'mensaje' => 'Tu acceso ha expirado. Contacta con la administración.']);
    exit;
}

// ── Login correcto ────────────────────────────────────────────
echo json_encode([
    'ok'     => true,
    'rol'    => $usuario['rol'],
    'id'     => (string) $usuario['id'],
    'email'  => $email,
    'nombre' => $usuario['nombre'],
]);
