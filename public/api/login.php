<?php
// ─────────────────────────────────────────────────────────────
// api/login.php  —  Autenticación con bcrypt + rate limiting + token de sesión
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

$body = json_decode(file_get_contents('php://input'), true);
if (!isset($body['email'], $body['contrasena'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Faltan campos']);
    exit;
}

require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();

// ── Rate limiting por IP ──────────────────────────────────────
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip = trim(explode(',', $ip)[0]);

$stmtIP = $pdo->prepare('SELECT intentos, bloqueado_hasta FROM login_intentos WHERE ip = :ip');
$stmtIP->execute([':ip' => $ip]);
$fila = $stmtIP->fetch();

if ($fila && $fila['bloqueado_hasta'] && new DateTime() < new DateTime($fila['bloqueado_hasta'])) {
    $resta = (int)ceil((new DateTime($fila['bloqueado_hasta']))->getTimestamp() - time());
    $min   = ceil($resta / 60);
    echo json_encode(['ok' => false, 'mensaje' => "Demasiados intentos. Espera {$min} min."]);
    exit;
}

// ── Buscar usuario ─────────────────────────────────────────────
$email = trim(strtolower($body['email']));
$stmt  = $pdo->prepare(
    'SELECT id, rol, nombre, contrasena, fecha_baja FROM usuarios WHERE email = :email AND activo = 1 LIMIT 1'
);
$stmt->execute([':email' => $email]);
$usuario = $stmt->fetch();

// ── Función para registrar fallo ───────────────────────────────
function registrarFallo(PDO $pdo, string $ip): void {
    $maxIntentos = 5;
    $bloqueoDur  = 15; // minutos
    $pdo->prepare(
        'INSERT INTO login_intentos (ip, intentos) VALUES (:ip, 1)
         ON DUPLICATE KEY UPDATE
           intentos       = intentos + 1,
           bloqueado_hasta = IF(intentos + 1 >= :max,
             DATE_ADD(NOW(), INTERVAL :dur MINUTE), NULL)'
    )->execute([':ip' => $ip, ':max' => $maxIntentos, ':dur' => $bloqueoDur]);
}

// ── Verificar contraseña ───────────────────────────────────────
$ok = false;
if ($usuario) {
    $hashGuardado = $usuario['contrasena'];
    $esBcrypt     = str_starts_with($hashGuardado, '$2y$') || str_starts_with($hashGuardado, '$2b$');

    if ($esBcrypt) {
        $ok = password_verify($body['contrasena'], $hashGuardado);
    } else {
        // Contraseña legacy SHA-256 — verificar y migrar automáticamente
        $ok = hash_equals($hashGuardado, hash('sha256', $body['contrasena']));
        if ($ok) {
            $nuevoHash = password_hash($body['contrasena'], PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare('UPDATE usuarios SET contrasena = ? WHERE id = ?')
                ->execute([$nuevoHash, $usuario['id']]);
        }
    }
}

if (!$ok) {
    registrarFallo($pdo, $ip);
    echo json_encode(['ok' => false, 'mensaje' => 'Credenciales incorrectas']);
    exit;
}

// ── Comprobar expiración de acceso ─────────────────────────────
if (!empty($usuario['fecha_baja']) && $usuario['fecha_baja'] < date('Y-m-d')) {
    echo json_encode(['ok' => false, 'mensaje' => 'Tu acceso ha expirado. Contacta con la administración.']);
    exit;
}

// ── Login correcto: limpiar intentos y generar token ──────────
$pdo->prepare('DELETE FROM login_intentos WHERE ip = :ip')->execute([':ip' => $ip]);

$token   = bin2hex(random_bytes(32)); // 64 chars
// 15 días: cubre tanto la sesión efímera de 8 h como la persistente
// del checkbox "Recordarme" (15 días en localStorage).
$expira  = date('Y-m-d H:i:s', strtotime('+15 days'));
$pdo->prepare('UPDATE usuarios SET token_sesion = :t, token_expira = :e WHERE id = :id')
    ->execute([':t' => $token, ':e' => $expira, ':id' => $usuario['id']]);

echo json_encode([
    'ok'     => true,
    'rol'    => $usuario['rol'],
    'id'     => (string) $usuario['id'],
    'email'  => $email,
    'nombre' => $usuario['nombre'],
    'token'  => $token,
]);
