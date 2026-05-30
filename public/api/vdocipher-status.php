<?php
// ─────────────────────────────────────────────────────────────
// api/vdocipher-status.php  —  Polling de estado de procesamiento
//
// GET ?material_id=X&token=Z  (solo admin)
//
// Consulta VdoCipher API, actualiza vdo_status en BD si cambia,
// y guarda duracion_seg cuando el vídeo pasa a "ready".
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
$token      = $_GET['token'] ?? '';
$dryRun     = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';

if (!$materialId || !$token) {
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

$stmt = $pdo->prepare(
    "SELECT u.id FROM usuarios u
     INNER JOIN sesiones s ON s.usuario_id = u.id
     WHERE s.token = :t AND s.expira_en > NOW() AND u.rol = 'admin'
     LIMIT 1"
);
$stmt->execute([':t' => $token]);
if (!$stmt->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Sin permisos']);
    exit;
}

// ── Obtener material ──────────────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT vdocipher_video_id, vdo_status, duracion_seg FROM materiales WHERE id = :id AND tipo = 'video'"
);
$stmt->execute([':id' => $materialId]);
$mat = $stmt->fetch();

if (!$mat || !$mat['vdocipher_video_id']) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'mensaje' => 'Material VdoCipher no encontrado']);
    exit;
}

if ($mat['vdo_status'] === 'ready') {
    echo json_encode(['ok' => true, 'status' => 'ready', 'actualizado' => false]);
    exit;
}

// ── Consultar VdoCipher ───────────────────────────────────────
$apiKey = defined('VDOCIPHER_API_KEY') ? VDOCIPHER_API_KEY : '';
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'VDOCIPHER_API_KEY no configurada']);
    exit;
}

$ch = curl_init("https://dev.vdocipher.com/api/videos/{$mat['vdocipher_video_id']}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET        => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Apisecret ' . $apiKey],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$respuesta  = curl_exec($ch);
$httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError  = curl_error($ch);
curl_close($ch);

if ($curlError || $httpStatus !== 200) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al consultar VdoCipher',
                      'detalle' => $curlError ?: "HTTP {$httpStatus}"]);
    exit;
}

$info = json_decode($respuesta, true);
$vdoStatusRaw = strtolower($info['status'] ?? 'processing');

// Mapeo a nuestro ENUM:
//  - 'Pre' / 'Pre-Upload' = se obtuvo la política pero el archivo aún no
//    se ha terminado de subir a S3  →  uploading
//  - 'Queued' / 'Queue'   = el archivo ya está en VdoCipher y se está
//    codificando/encriptando        →  processing
//  - 'Encoding'           = codificando explícito  →  processing
//  - 'ready'              = listo para reproducir  →  ready
$nuestroStatus = match(true) {
    $vdoStatusRaw === 'ready'                                  => 'ready',
    in_array($vdoStatusRaw, ['pre', 'pre-upload'], true)        => 'uploading',
    in_array($vdoStatusRaw, ['queued', 'queue', 'encoding'], true) => 'processing',
    default                                                     => 'processing',
};

// Decidir qué hay que actualizar.
//   - Si cambia el estado → actualizar status.
//   - Si VdoCipher devuelve duración y en BD está NULL → rellenar la duración
//     (cubre los casos en que vdo_status='ready' quedó persistido pero la
//     duración nunca llegó a guardarse).
$cambiaStatus      = $nuestroStatus !== $mat['vdo_status'];
$duraciónVdo       = (!empty($info['length']) && (int)$info['length'] > 0) ? (int)$info['length'] : null;
$rellenarDuracion  = $nuestroStatus === 'ready' && $duraciónVdo !== null && $mat['duracion_seg'] === null;
$duracionSeg       = $rellenarDuracion ? $duraciónVdo : null;
$actualizado       = false;

if (($cambiaStatus || $rellenarDuracion) && !$dryRun) {
    $sets   = [];
    $params = [':id' => $materialId];
    if ($cambiaStatus) {
        $sets[] = 'vdo_status = :status';
        $params[':status'] = $nuestroStatus;
    }
    if ($rellenarDuracion) {
        $sets[] = 'duracion_seg = :dur';
        $params[':dur'] = $duraciónVdo;
    }
    $pdo->prepare('UPDATE materiales SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    $actualizado = true;
}

echo json_encode([
    'ok'           => true,
    'status'       => $nuestroStatus,
    'duracion_seg' => $duracionSeg,
    'dry_run'      => $dryRun,
    'actualizado'  => $actualizado,
]);
