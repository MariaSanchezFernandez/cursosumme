<?php
// sync-vdo-status.php — Sincroniza vdo_status con VdoCipher para vídeos no "ready"
// Uso único protegido con SETUP_KEY. Uso: GET /api/sync-vdo-status.php?key=...
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db-connect.php';
if (($_GET['key'] ?? '') !== SETUP_KEY) { http_response_code(403); exit; }

$pdo = obtenerPDO();
$apiKey = defined('VDOCIPHER_API_KEY') ? VDOCIPHER_API_KEY : '';
if (!$apiKey) { echo json_encode(['ok' => false, 'mensaje' => 'VDOCIPHER_API_KEY no configurada']); exit; }

$materiales = $pdo->query(
    "SELECT id, vdocipher_video_id FROM materiales
     WHERE vdocipher_video_id IS NOT NULL AND vdo_status != 'ready'"
)->fetchAll();

$actualizados = [];
$errores = [];

foreach ($materiales as $mat) {
    $ch = curl_init("https://dev.vdocipher.com/api/videos/{$mat['vdocipher_video_id']}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Apisecret ' . $apiKey],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) {
        $errores[] = ['id' => $mat['id'], 'vdo_id' => $mat['vdocipher_video_id'], 'http' => $status];
        continue;
    }

    $data = json_decode($resp, true);
    // VdoCipher devuelve status: "ready", "processing", "queued", "error"
    $vdoStatus = $data['status'] ?? '';
    $nuevoStatus = match($vdoStatus) {
        'ready'      => 'ready',
        'processing', 'queued' => 'processing',
        default      => null,
    };

    if ($nuevoStatus === null) {
        $errores[] = ['id' => $mat['id'], 'vdo_id' => $mat['vdocipher_video_id'], 'vdo_status' => $vdoStatus];
        continue;
    }

    $duracion = null;
    if ($nuevoStatus === 'ready' && !empty($data['length'])) {
        $duracion = (int)round((float)$data['length']);
    }

    if ($duracion !== null) {
        $pdo->prepare('UPDATE materiales SET vdo_status = :s, duracion_seg = :d WHERE id = :id')
            ->execute([':s' => $nuevoStatus, ':d' => $duracion, ':id' => $mat['id']]);
    } else {
        $pdo->prepare('UPDATE materiales SET vdo_status = :s WHERE id = :id')
            ->execute([':s' => $nuevoStatus, ':id' => $mat['id']]);
    }

    $actualizados[] = ['id' => $mat['id'], 'vdo_id' => $mat['vdocipher_video_id'], 'status' => $nuevoStatus, 'duracion_seg' => $duracion];
}

echo json_encode([
    'ok'          => true,
    'actualizados' => $actualizados,
    'errores'      => $errores,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
