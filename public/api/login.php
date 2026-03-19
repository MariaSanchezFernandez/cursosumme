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
$dsn = 'mysql:host=db5020047845.hosting-data.io;port=3306;dbname=dbs15459256;charset=utf8mb4';

try {
    $pdo = new PDO($dsn, 'dbs15459256', 'cursosumme123', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5,
    ]);
} catch (PDOException $e) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'mensaje' => 'Error de conexión con la base de datos']);
    exit;
}

// ── Buscar usuario activo por email ──────────────────────────
$stmt = $pdo->prepare(
    'SELECT rol, contrasena FROM usuarios WHERE email = :email AND activo = 1 LIMIT 1'
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
echo json_encode(['ok' => true, 'rol' => $usuario['rol']]);
