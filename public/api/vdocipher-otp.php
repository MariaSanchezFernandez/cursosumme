<?php
// ─────────────────────────────────────────────────────────────
// api/vdocipher-otp.php  —  Genera OTP para reproducción DRM
//
// GET ?material_id=X&usuario_id=Y&token=Z
//
// Valida acceso (admin o alumno matriculado) y devuelve
// { otp, playbackInfo } para cargar el iframe de VdoCipher:
//   https://player.vdocipher.com/v2/?otp=…&playbackInfo=…
// OTP TTL: 5 minutos (single-use, DRM Widevine + FairPlay).
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$materialId = isset($_GET['material_id']) ? (int)$_GET['material_id'] : 0;
$usuarioId  = isset($_GET['usuario_id'])  ? (int)$_GET['usuario_id']  : 0;
$token      = $_GET['token'] ?? '';

if (!$materialId || !$usuarioId || !$token) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Faltan parámetros']);
    exit;
}
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'Token inválido']);
    exit;
}

require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();

$authStmt = $pdo->prepare(
    'SELECT u.rol, u.email FROM usuarios u
     INNER JOIN sesiones s ON s.usuario_id = u.id
     WHERE u.id = :id AND s.token = :t AND s.expira_en > NOW()
     LIMIT 1'
);
$authStmt->execute([':id' => $usuarioId, ':t' => $token]);
$authRow = $authStmt->fetch(PDO::FETCH_ASSOC);
$rol      = $authRow['rol']   ?? null;
$email    = $authRow['email'] ?? '';
if (!$rol) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'Sesión inválida o expirada']);
    exit;
}

// ── Obtener videoId y verificar acceso ────────────────────────
if ($rol === 'admin') {
    $stmt = $pdo->prepare(
        "SELECT vdocipher_video_id FROM materiales WHERE id = :mid AND tipo = 'video'"
    );
    $stmt->execute([':mid' => $materialId]);
} else {
    // Bloqueo per-alumna: solo aplica a alumnas marcadas como es_alumna_rocio=1.
    // El LEFT JOIN trae el bloqueo de ESTA alumna sobre ESTE tema (si existe);
    // la WHERE descarta el material si la alumna está bloqueada en el futuro.
    $stmt = $pdo->prepare(
        "SELECT m.vdocipher_video_id
         FROM materiales m
         INNER JOIN temas t ON t.id = m.tema_id
         INNER JOIN usuarios_cursos uc ON uc.curso_id = t.curso_id
         INNER JOIN cursos c ON c.id = t.curso_id
         INNER JOIN usuarios u ON u.id = uc.usuario_id
         LEFT JOIN temas_bloqueos_alumno b
                ON b.usuario_id = uc.usuario_id AND b.tema_id = t.id
         WHERE m.id = :mid AND m.tipo = 'video'
           AND uc.usuario_id = :uid AND c.activo = 1
           AND (u.es_alumna_rocio = 0 OR b.bloqueado_hasta IS NULL OR b.bloqueado_hasta <= NOW())"
    );
    $stmt->execute([':mid' => $materialId, ':uid' => $usuarioId]);
}

$videoId = $stmt->fetchColumn();
if (!$videoId) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado o vídeo no encontrado']);
    exit;
}

// ── Generar OTP ───────────────────────────────────────────────
$apiKey = defined('VDOCIPHER_API_KEY') ? VDOCIPHER_API_KEY : '';
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'VDOCIPHER_API_KEY no configurada']);
    exit;
}

// Watermark dinámico con el email del alumno + bloqueo de captura.
//
// El Custom Player Theme (VDOCIPHER_PLAYER_ID) DESACTIVA la protección
// anti-captura en Chrome desktop — comprobado empíricamente el 2026-05-25
// contrastando default vs custom con el mismo OTP/Mac/Chrome. Por eso
// dejamos de pasar el player ID al frontend.
//
// Como el watermark estaba atado al Custom Player Theme, lo reinyectamos
// vía el parámetro `annotate` del OTP (ver doc 3.2.2).
$annotate = $email !== ''
    ? json_encode([[
        'type'     => 'rtext',
        'text'     => $email,
        'alpha'    => '0.60',
        'color'    => '0xFFFFFF',
        'size'     => '15',
        'interval' => '5000',
        'skip'     => '5000',
    ]])
    : null;

$otpBody = ['ttl' => 300];
if ($annotate) $otpBody['annotate'] = $annotate;

$ch = curl_init("https://dev.vdocipher.com/api/videos/{$videoId}/otp");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($otpBody),
    CURLOPT_HTTPHEADER     => [
        'Authorization: Apisecret ' . $apiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$respuesta  = curl_exec($ch);
$httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError  = curl_error($ch);
curl_close($ch);

if ($curlError || $httpStatus !== 200) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al generar el token de reproducción',
                      'detalle' => $curlError ?: "HTTP {$httpStatus}", 'vdo_response' => $respuesta]);
    exit;
}

$otp = json_decode($respuesta, true);
if (empty($otp['otp']) || empty($otp['playbackInfo'])) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'mensaje' => 'Respuesta OTP inválida de VdoCipher']);
    exit;
}

echo json_encode(['ok' => true, 'otp' => $otp['otp'], 'playbackInfo' => $otp['playbackInfo']]);
