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
require_once __DIR__ . '/db-config.php';

// IONOS shared hosting: intentamos primero localhost, luego el host externo
$hosts = [DB_HOST_1, DB_HOST_2];
$pdo = null;

foreach ($hosts as $host) {
    try {
        $dsn = "mysql:host={$host};port=3306;dbname=" . DB_NOMBRE . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USUARIO, DB_PASSWORD, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
        break; // conexión exitosa
    } catch (PDOException $e) {
        $pdo = null;
    }
}

if ($pdo === null) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'mensaje' => 'Error de conexión con la base de datos']);
    exit;
}

// ── Buscar usuario activo por email ──────────────────────────
$stmt = $pdo->prepare(
    'SELECT id, rol, contrasena FROM usuarios WHERE email = :email AND activo = 1 LIMIT 1'
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

// ── Login correcto ────────────────────────────────────────────
echo json_encode([
    'ok'    => true,
    'rol'   => $usuario['rol'],
    'id'    => (string) $usuario['id'],
    'email' => $email,
]);
