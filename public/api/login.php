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

// ── Identificar dispositivo ───────────────────────────────────
// Estrategia: UUID persistente generado en el navegador (localStorage)
// enviado como device_id en el body. No depende de la IP, sobrevive
// cambios de red (4G↔WiFi, IP dinámica del ISP).
//
// Si el cliente no envía device_id válido (petición directa a la API,
// navegador sin JS, o localStorage que no persiste — Safari ITP borra
// storage tras inactividad, modo privado, etc.) NO se genera un UUID
// nuevo: se guarda NULL. Generar uno aleatorio creaba una fila "fantasma"
// distinta en cada login de ese dispositivo, que se acumulaba hasta
// agotar el límite de sesiones aunque fuera siempre el mismo aparato.
$deviceIdCliente = trim($body['device_id'] ?? '');
$deviceId = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $deviceIdCliente)
    ? $deviceIdCliente
    : null;

// Etiqueta legible del dispositivo (solo OS, sin navegador — quien usa
// Chrome y Firefox en el mismo equipo no debe consumir dos slots).
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300);
function parsearDispositivo(string $ua): string {
    if (str_contains($ua, 'iPad'))                         return 'iPad';
    if (str_contains($ua, 'iPhone'))                       return 'iPhone';
    if (preg_match('/Android/i', $ua))                     return 'Android';
    if (preg_match('/Windows/i', $ua))                     return 'Windows';
    if (preg_match('/Macintosh|Mac OS X/i', $ua))          return 'Mac';
    if (preg_match('/Linux/i', $ua))                       return 'Linux';
    return 'Desconocido';
}
$dispositivo = $ua ? parsearDispositivo($ua) : 'Desconocido';

if ($deviceId !== null) {
    // Si este device_id ya tenía una sesión activa, la reemplazamos —
    // re-login desde el mismo navegador no consume un slot nuevo.
    $pdo->prepare(
        'DELETE FROM sesiones WHERE usuario_id = :id AND device_id = :did'
    )->execute([':id' => $usuario['id'], ':did' => $deviceId]);
} else {
    // Sin device_id rastreable no podemos saber si es el mismo aparato
    // de antes, así que TODAS las sesiones no rastreables de esta cuenta
    // comparten un único slot (la nueva sustituye a la anterior) en vez
    // de crear una fila distinta por login.
    $pdo->prepare(
        'DELETE FROM sesiones WHERE usuario_id = :id AND device_id IS NULL'
    )->execute([':id' => $usuario['id']]);
}

// Contar slots activos distintos. Las sesiones sin device_id (legacy o
// sin localStorage persistente) cuentan juntas como un único slot — ver
// DELETE de arriba, nunca hay más de una fila con device_id IS NULL.
$stCount = $pdo->prepare(
    "SELECT COUNT(DISTINCT COALESCE(device_id, 'sin-device'))
     FROM sesiones WHERE usuario_id = :id AND expira_en > NOW()"
);
$stCount->execute([':id' => $usuario['id']]);
$activasCuenta = (int)$stCount->fetchColumn();

if ($usuario['rol'] !== 'admin') {
    $stMax = $pdo->prepare('SELECT max_sesiones FROM usuarios WHERE id = :id LIMIT 1');
    $stMax->execute([':id' => $usuario['id']]);
    $maxSesiones = (int)$stMax->fetchColumn() ?: 3;

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
    'INSERT INTO sesiones (usuario_id, token, ip, dispositivo, device_id, expira_en)
     VALUES (:uid, :t, :ip, :d, :did, :e)'
)->execute([':uid' => $usuario['id'], ':t' => $token, ':ip' => $ip, ':d' => $dispositivo, ':did' => $deviceId, ':e' => $expira]);

echo json_encode([
    'ok'        => true,
    'rol'       => $usuario['rol'],
    'id'        => (string) $usuario['id'],
    'email'     => $email,
    'nombre'    => $usuario['nombre'],
    'token'     => $token,
    'fecha_baja'=> $usuario['fecha_baja'] ?? null,
]);
