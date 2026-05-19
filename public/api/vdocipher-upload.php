<?php
// ─────────────────────────────────────────────────────────────
// api/vdocipher-upload.php  —  Inicia subida de vídeo a VdoCipher
//
// POST ?token=X  { tema_id, nombre_original, duracion_seg? }
//   Solo admin. IONOS elimina el header Authorization, por eso
//   se acepta el token por query string (igual que video.php).
//
// 1. Llama PUT https://dev.vdocipher.com/api/videos?title=...
// 2. Crea registro en materiales con vdo_status='uploading'.
// 3. Devuelve { material_id, videoId, clientPayload }.
//    El cliente sube el archivo DIRECTO al S3 de VdoCipher.
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

set_exception_handler(function (Throwable $e) {
    if (!headers_sent()) http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error interno', 'detalle' => $e->getMessage()]);
    exit;
});

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
$pdo = obtenerPDO();

// ── Auth: mismo patrón que video.php (usuario_id + token) ────
// IONOS filtra el header Authorization; se acepta token por query string.
$token     = $_GET['token']      ?? '';
$usuarioId = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'No autenticado']);
    exit;
}
// Si llega uid lo usamos (más específico); si no, buscamos solo por token
if ($usuarioId > 0) {
    $stmt = $pdo->prepare(
        "SELECT id FROM usuarios WHERE id = :uid AND token_sesion = :t AND token_expira > NOW() AND rol = 'admin'"
    );
    $stmt->execute([':uid' => $usuarioId, ':t' => $token]);
} else {
    $stmt = $pdo->prepare(
        "SELECT id FROM usuarios WHERE token_sesion = :t AND token_expira > NOW() AND rol = 'admin'"
    );
    $stmt->execute([':t' => $token]);
}
$adminId = (int)($stmt->fetchColumn() ?: 0);
if (!$adminId) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Sin permisos', 'debug' => 'token recibido: ' . substr($token,0,8) . '…']);
    exit;
}

// ── Parámetros (query string para compatibilidad con IONOS) ──
$temaId      = isset($_GET['tema_id'])        ? (int)$_GET['tema_id']        : 0;
$titulo      = isset($_GET['nombre_original']) ? trim($_GET['nombre_original']) : '';
$duracionSeg = isset($_GET['duracion_seg']) && (int)$_GET['duracion_seg'] > 0
               ? (int)$_GET['duracion_seg'] : null;

if (!$temaId || $titulo === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Faltan parámetros: tema_id y nombre_original']);
    exit;
}

$ext = strtolower(pathinfo($titulo, PATHINFO_EXTENSION));
if (!in_array($ext, ['mp4', 'webm', 'mov', 'avi', 'm4v', 'mkv'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => "Formato de vídeo no permitido (.{$ext})"]);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM temas WHERE id = :id');
$stmt->execute([':id' => $temaId]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'mensaje' => 'Tema no encontrado']);
    exit;
}

// ── API key ───────────────────────────────────────────────────
$apiKey = defined('VDOCIPHER_API_KEY') ? VDOCIPHER_API_KEY : '';
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'VDOCIPHER_API_KEY no está definida en db-config.php']);
    exit;
}

// ── Solicitar credenciales a VdoCipher (PUT) ──────────────────
$ch = curl_init('https://dev.vdocipher.com/api/videos?title=' . rawurlencode($titulo));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'PUT',
    CURLOPT_HTTPHEADER     => [
        'Authorization: Apisecret ' . $apiKey,
        'Content-Length: 0',
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$respuesta  = curl_exec($ch);
$httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError  = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'mensaje' => 'Error de red al contactar VdoCipher', 'detalle' => $curlError]);
    exit;
}
if ($httpStatus !== 200) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'mensaje' => "VdoCipher respondió HTTP {$httpStatus}", 'raw' => $respuesta]);
    exit;
}

$vdo = json_decode($respuesta, true);
if (empty($vdo['videoId']) || empty($vdo['clientPayload'])) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'mensaje' => 'Respuesta inesperada de VdoCipher', 'raw' => $respuesta]);
    exit;
}

// ── Crear registro en BD ──────────────────────────────────────
$ins = $pdo->prepare(
    'INSERT INTO materiales
       (tema_id, tipo, nombre, ruta, tamano_kb, duracion_seg, vdocipher_video_id, vdo_status)
     VALUES (:tema_id, :tipo, :nombre, :ruta, 0, :dur, :vdo_id, :vdo_status)'
);
$ins->execute([
    ':tema_id'    => $temaId,
    ':tipo'       => 'video',
    ':nombre'     => $titulo,
    ':ruta'       => null,
    ':dur'        => $duracionSeg,
    ':vdo_id'     => $vdo['videoId'],
    ':vdo_status' => 'uploading',
]);
$materialId = (int)$pdo->lastInsertId();

registrar_log($pdo, 'vdocipher_upload_iniciado',
    "VdoCipher: iniciada subida \"{$titulo}\" → videoId={$vdo['videoId']} (tema {$temaId})", $adminId);

echo json_encode([
    'ok'            => true,
    'material_id'   => $materialId,
    'videoId'       => $vdo['videoId'],
    'clientPayload' => $vdo['clientPayload'],
]);
