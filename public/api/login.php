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

// ── Login correcto: limpiar intentos ─────────────────────────
$pdo->prepare('DELETE FROM login_intentos WHERE ip = :ip')->execute([':ip' => $ip]);

// Limpiar sesiones expiradas de este usuario (mantenimiento preventivo)
$pdo->prepare('DELETE FROM sesiones WHERE usuario_id = :id AND expira_en < NOW()')
    ->execute([':id' => $usuario['id']]);

// Parsear el dispositivo ANTES del control de límite — necesitamos saber
// si este login es desde un dispositivo que ya tenía sesión (en cuyo caso
// no cuenta como sesión nueva, simplemente se reemplaza).
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300);
function parsearDispositivo(string $ua): string {
    $os = 'Desconocido';
    if (preg_match('/iPhone|iPad/i', $ua))         $os = str_contains($ua, 'iPad') ? 'iPad' : 'iPhone';
    elseif (preg_match('/Android/i', $ua))          $os = 'Android';
    elseif (preg_match('/Windows/i', $ua))          $os = 'Windows';
    elseif (preg_match('/Macintosh|Mac OS X/i', $ua)) $os = 'Mac';
    elseif (preg_match('/Linux/i', $ua))            $os = 'Linux';

    $nav = 'Navegador';
    if (preg_match('/Edg\//i', $ua))                $nav = 'Edge';
    elseif (preg_match('/OPR\//i', $ua))            $nav = 'Opera';
    elseif (preg_match('/Chrome\//i', $ua))         $nav = 'Chrome';
    elseif (preg_match('/Firefox\//i', $ua))        $nav = 'Firefox';
    elseif (preg_match('/Safari\//i', $ua))         $nav = 'Safari';

    return $nav . ' · ' . $os;
}
$dispositivo = $ua ? parsearDispositivo($ua) : 'Dispositivo desconocido';

// El límite se cuenta por DISPOSITIVO ÚNICO (etiqueta + IP), no por token.
// Si este login es desde un dispositivo+IP que ya tenía sesión activa, la
// borramos primero — el nuevo token reemplaza al anterior y no suma al
// contador.
$pdo->prepare(
    'DELETE FROM sesiones
     WHERE usuario_id = :id AND dispositivo = :d AND ip = :ip'
)->execute([':id' => $usuario['id'], ':d' => $dispositivo, ':ip' => $ip]);

// Ahora sí, contar dispositivos activos distintos
$stCount = $pdo->prepare(
    'SELECT COUNT(DISTINCT CONCAT(dispositivo, "|", ip))
     FROM sesiones WHERE usuario_id = :id AND expira_en > NOW()'
);
$stCount->execute([':id' => $usuario['id']]);
$activasCuenta = (int)$stCount->fetchColumn();

if ($usuario['rol'] !== 'admin') {
    $stMax = $pdo->prepare('SELECT max_sesiones FROM usuarios WHERE id = :id LIMIT 1');
    $stMax->execute([':id' => $usuario['id']]);
    $maxSesiones = (int)$stMax->fetchColumn() ?: 2;

    if ($activasCuenta >= $maxSesiones) {
        $msg = $maxSesiones === 1
            ? 'Ya hay una sesión activa con esta cuenta en otro dispositivo. Cierra sesión allí para continuar.'
            : "Ya hay {$activasCuenta} dispositivos con sesión activa (límite: {$maxSesiones}). Cierra sesión en otro para continuar.";
        echo json_encode(['ok' => false, 'mensaje' => $msg]);
        exit;
    }
}

// Generar e insertar nueva sesión
$token  = bin2hex(random_bytes(32));
$expira = date('Y-m-d H:i:s', strtotime('+15 days'));
$pdo->prepare(
    'INSERT INTO sesiones (usuario_id, token, ip, dispositivo, expira_en) VALUES (:uid, :t, :ip, :d, :e)'
)->execute([':uid' => $usuario['id'], ':t' => $token, ':ip' => $ip, ':d' => $dispositivo, ':e' => $expira]);

echo json_encode([
    'ok'        => true,
    'rol'       => $usuario['rol'],
    'id'        => (string) $usuario['id'],
    'email'     => $email,
    'nombre'    => $usuario['nombre'],
    'token'     => $token,
    'fecha_baja'=> $usuario['fecha_baja'] ?? null,
]);
