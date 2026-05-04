<?php
// ─────────────────────────────────────────────────────────────
// api/cloudflare-token.php  —  Genera un JWT firmado para
// reproducción segura de vídeos de Cloudflare Stream.
// GET  ?material_id=X&usuario_id=Y&token=Z
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/db-config.php';

$pdo = obtenerPDO();

// Validar sesión por query param (igual que video.php)
$tokenSesion = $_GET['token'] ?? '';
$usuarioId   = (int)($_GET['usuario_id'] ?? 0);
$materialId  = (int)($_GET['material_id'] ?? 0);

if (!$tokenSesion || !$usuarioId || !$materialId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Parámetros insuficientes']);
    exit;
}

$uStmt = $pdo->prepare(
    'SELECT id FROM usuarios WHERE id=:id AND token_sesion=:t AND token_expira > NOW() AND activo=1 LIMIT 1'
);
$uStmt->execute([':id' => $usuarioId, ':t' => $tokenSesion]);
if (!$uStmt->fetch()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'Sesión inválida']);
    exit;
}

// Obtener cf_video_id del material
$mStmt = $pdo->prepare('SELECT cf_video_id, cf_status FROM materiales WHERE id=:id LIMIT 1');
$mStmt->execute([':id' => $materialId]);
$mat = $mStmt->fetch();

if (!$mat || !$mat['cf_video_id']) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'mensaje' => 'Vídeo no encontrado']);
    exit;
}

if ($mat['cf_status'] !== 'ready') {
    echo json_encode(['ok' => false, 'mensaje' => 'procesando']);
    exit;
}

// Generar JWT firmado con RS256
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$videoId = $mat['cf_video_id'];
$keyId   = CF_SIGNING_KEY_ID;
$pemB64  = CF_SIGNING_KEY_PEM;
$pem     = base64_decode($pemB64);

$header  = ['alg' => 'RS256', 'kid' => $keyId];
$payload = [
    'sub' => $videoId,
    'kid' => $keyId,
    'exp' => time() + 3600,  // 1 hora
    'accessRules' => [['type' => 'any', 'action' => 'allow']],
];

$headerEnc  = base64url_encode(json_encode($header));
$payloadEnc = base64url_encode(json_encode($payload));
$input      = $headerEnc . '.' . $payloadEnc;

$privateKey = openssl_pkey_get_private($pem);
if (!$privateKey) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al cargar clave de firma']);
    exit;
}

openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256);
$token = $input . '.' . base64url_encode($signature);

echo json_encode(['ok' => true, 'token' => $token]);
