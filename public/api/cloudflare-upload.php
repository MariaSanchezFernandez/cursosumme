<?php
// ─────────────────────────────────────────────────────────────
// api/cloudflare-upload.php  —  Crea un slot de subida TUS en
// Cloudflare Stream y registra el material en BD.
// POST  body JSON: { tema_id, nombre_original, duracion_seg? }
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/db-config.php';
require_once __DIR__ . '/log-helper.php';

$pdo = obtenerPDO();

// Aceptar token por query param (IONOS filtra la cabecera Authorization en CGI)
if (!empty($_GET['token']) && empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $_GET['token'];
}
$admin = requireAdmin($pdo);

$body        = json_decode(file_get_contents('php://input'), true) ?? [];
$temaId      = (int)($body['tema_id']      ?? 0);
$nombreOrig  = trim($body['nombre_original'] ?? '');
$duracionSeg = isset($body['duracion_seg']) ? (int)$body['duracion_seg'] : null;
$fileSize    = (int)($body['file_size'] ?? 0);

if (!$temaId || !$nombreOrig || $fileSize <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Faltan parámetros obligatorios']);
    exit;
}

// 1. Pedir a Cloudflare un slot de subida TUS directa desde el navegador
$metadataParts = [
    'name '             . base64_encode($nombreOrig),
    'requiresignedurls',
];
if ($duracionSeg > 0) {
    $metadataParts[] = 'duration ' . base64_encode((string)$duracionSeg);
}
$metadata = implode(',', $metadataParts);

$ch = curl_init('https://api.cloudflare.com/client/v4/accounts/' . CF_ACCOUNT_ID . '/stream?direct_user=true');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => '',
    CURLOPT_HEADER         => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . CF_API_TOKEN,
        'Tus-Resumable: 1.0.0',
        'Upload-Length: ' . $fileSize,
        'Upload-Metadata: ' . $metadata,
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response   = curl_exec($ch);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

if ($httpCode !== 201) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'mensaje' => "Cloudflare respondió HTTP {$httpCode}"]);
    exit;
}

$headers = substr($response, 0, $headerSize);

// Extraer Location y stream-media-id de las cabeceras
$uploadUrl = '';
$videoId   = '';
foreach (explode("\r\n", $headers) as $line) {
    if (stripos($line, 'location:') === 0) {
        $uploadUrl = trim(substr($line, strlen('location:')));
    }
    if (stripos($line, 'stream-media-id:') === 0) {
        $videoId = trim(substr($line, strlen('stream-media-id:')));
    }
}

if (!$uploadUrl || !$videoId) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'mensaje' => 'Cloudflare no devolvió URL de subida']);
    exit;
}

// 2. Registrar el material en BD con estado "uploading"
$stmt = $pdo->prepare(
    'INSERT INTO materiales (tema_id, tipo, nombre, ruta, tamano_kb, duracion_seg, cf_video_id, cf_status)
     VALUES (:tema_id, :tipo, :nombre, NULL, 0, :dur, :cf_id, "uploading")'
);
$stmt->execute([
    ':tema_id' => $temaId,
    ':tipo'    => 'video',
    ':nombre'  => $nombreOrig,
    ':dur'     => $duracionSeg,
    ':cf_id'   => $videoId,
]);
$materialId = (int)$pdo->lastInsertId();

registrar_log($pdo, 'cf_upload_iniciado', "Vídeo \"{$nombreOrig}\" subida iniciada (CF: {$videoId})", $admin['id']);

echo json_encode([
    'ok'          => true,
    'upload_url'  => $uploadUrl,
    'material_id' => $materialId,
    'video_id'    => $videoId,
]);
