<?php
// ─────────────────────────────────────────────────────────────
// api/cloudflare-status.php  —  Consulta el estado de procesado
// de un vídeo en Cloudflare Stream y actualiza la BD.
// GET  ?material_id=X&token=Z   (token de sesión admin)
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/db-config.php';
require_once __DIR__ . '/log-helper.php';

$pdo = obtenerPDO();
if (!empty($_GET['token']) && empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $_GET['token'];
}
requireAdmin($pdo);

$materialId = (int)($_GET['material_id'] ?? 0);
if (!$materialId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Falta material_id']);
    exit;
}

$stmt = $pdo->prepare('SELECT cf_video_id, cf_status, duracion_seg FROM materiales WHERE id=:id LIMIT 1');
$stmt->execute([':id' => $materialId]);
$mat = $stmt->fetch();

if (!$mat || !$mat['cf_video_id']) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'mensaje' => 'Material no encontrado']);
    exit;
}

if ($mat['cf_status'] === 'ready') {
    echo json_encode(['ok' => true, 'status' => 'ready', 'duracion_seg' => $mat['duracion_seg']]);
    exit;
}

// Consultar a Cloudflare
$ch = curl_init('https://api.cloudflare.com/client/v4/accounts/' . CF_ACCOUNT_ID . '/stream/' . rawurlencode($mat['cf_video_id']));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . CF_API_TOKEN],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200) {
    echo json_encode(['ok' => true, 'status' => $mat['cf_status']]);
    exit;
}

$data  = json_decode($res, true);
$state = $data['result']['status']['state'] ?? 'processing';

$cfStatus = match($state) {
    'ready'    => 'ready',
    'error'    => 'error',
    default    => 'processing',
};

// Extraer duración si está lista
$durSeg = null;
if ($cfStatus === 'ready') {
    $durFloat = $data['result']['duration'] ?? null;
    if ($durFloat > 0) $durSeg = (int)round($durFloat);
}

// Actualizar BD si cambió el estado
if ($cfStatus !== $mat['cf_status']) {
    $upd = $pdo->prepare(
        'UPDATE materiales SET cf_status=:s' . ($durSeg !== null ? ', duracion_seg=:d' : '') . ' WHERE id=:id'
    );
    $params = [':s' => $cfStatus, ':id' => $materialId];
    if ($durSeg !== null) $params[':d'] = $durSeg;
    $upd->execute($params);

    if ($cfStatus === 'ready') {
        registrar_log($pdo, 'cf_video_ready', "Vídeo CF {$mat['cf_video_id']} listo", 0);
    }
}

echo json_encode(['ok' => true, 'status' => $cfStatus, 'duracion_seg' => $durSeg ?? $mat['duracion_seg']]);
