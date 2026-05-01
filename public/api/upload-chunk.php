<?php
// ─────────────────────────────────────────────────────────────
// api/upload-chunk.php  —  Subida de vídeos por chunks (raw stream)
//
// Parámetros en query string:
//   upload_id, chunk_index, total_chunks, total_size,
//   tema_id, nombre_original, duracion_seg (solo último chunk)
//
// Cuerpo de la petición: bytes crudos del chunk
//   (Content-Type: application/octet-stream)
//
// El chunk se escribe directamente sobre un archivo .part en
// uploads/videos/, sin pasar por /tmp. Al recibir el último chunk
// se renombra al nombre final y se registra en BD.
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$uploadId    = $_GET['upload_id']             ?? '';
$chunkIndex  = isset($_GET['chunk_index'])    ? (int)$_GET['chunk_index']  : -1;
$totalChunks = isset($_GET['total_chunks'])   ? (int)$_GET['total_chunks'] : 0;
$totalSize   = isset($_GET['total_size'])     ? (int)$_GET['total_size']   : 0;
$temaId      = isset($_GET['tema_id'])        ? (int)$_GET['tema_id']      : 0;
$nombreOrig  = $_GET['nombre_original']       ?? '';
$duracionSeg = isset($_GET['duracion_seg'])   ? (int)$_GET['duracion_seg'] : 0;

if (!preg_match('/^[a-zA-Z0-9]{16,64}$/', $uploadId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'upload_id inválido']);
    exit;
}
if ($chunkIndex < 0 || $totalChunks < 1 || $chunkIndex >= $totalChunks || $temaId <= 0 || $nombreOrig === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Parámetros inválidos']);
    exit;
}

$extension = strtolower(pathinfo($nombreOrig, PATHINFO_EXTENSION));
if (!in_array($extension, ['mp4', 'webm', 'mov', 'avi', 'm4v', 'mkv'])) {
    echo json_encode(['ok' => false, 'mensaje' => "Tipo de vídeo no permitido (.{$extension})"]);
    exit;
}

$docRoot   = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$dirVideos = $docRoot . '/uploads/videos/';
$partPath  = $dirVideos . $uploadId . '.part';

// Crear directorio si no existe
if (!is_dir($dirVideos) && !mkdir($dirVideos, 0755, true) && !is_dir($dirVideos)) {
    echo json_encode([
        'ok'          => false,
        'mensaje'     => 'No se pudo crear el directorio de vídeos',
        'diagnostico' => ['disco_libre_mb' => (int)floor(@disk_free_space($docRoot . '/uploads/') / 1024 / 1024)],
    ]);
    exit;
}

// Limpiar archivos .part huérfanos (> 6 h) al iniciar un nuevo upload
if ($chunkIndex === 0) {
    $umbral = time() - 6 * 3600;
    foreach (glob($dirVideos . '*.part') ?: [] as $orfano) {
        if (@filemtime($orfano) < $umbral) {
            @unlink($orfano);
        }
    }
}

// Abrir fichero parcial: crear en chunk 0, añadir al final en los siguientes
$dst = @fopen($partPath, $chunkIndex === 0 ? 'wb' : 'ab');
if (!$dst) {
    $err = error_get_last();
    echo json_encode([
        'ok'          => false,
        'mensaje'     => 'No se pudo abrir el archivo temporal de vídeo',
        'diagnostico' => [
            'directorio_existe'  => is_dir($dirVideos),
            'directorio_escribe' => is_writable($dirVideos),
            'php_error'          => $err['message'] ?? null,
            'disco_libre_mb'     => (int)floor(@disk_free_space($dirVideos) / 1024 / 1024),
        ],
    ]);
    exit;
}

// Escribir el chunk leyendo directamente del cuerpo de la petición (sin /tmp)
$src     = fopen('php://input', 'rb');
$written = @stream_copy_to_stream($src, $dst);
fclose($src);
fclose($dst);

if ($written === false) {
    @unlink($partPath);
    $err = error_get_last();
    echo json_encode([
        'ok'          => false,
        'mensaje'     => 'Error al escribir el chunk en disco',
        'diagnostico' => [
            'php_error'      => $err['message'] ?? null,
            'disco_libre_mb' => (int)floor(@disk_free_space($dirVideos) / 1024 / 1024),
        ],
    ]);
    exit;
}

// Chunk intermedio: confirmar y esperar el siguiente
if ($chunkIndex < $totalChunks - 1) {
    echo json_encode(['ok' => true, 'recibido' => $chunkIndex]);
    exit;
}

// ─── Último chunk: validar tamaño y renombrar ───────────────
$partSize = filesize($partPath);

if ($totalSize > 0 && abs($partSize - $totalSize) > 1024) {
    @unlink($partPath);
    echo json_encode(['ok' => false, 'mensaje' => 'El tamaño del vídeo no coincide. Vuelve a intentarlo.']);
    exit;
}

if ($partSize > 3584 * 1024 * 1024) {
    @unlink($partPath);
    echo json_encode(['ok' => false, 'mensaje' => 'El vídeo supera el límite de 3.5 GB']);
    exit;
}

$nombreSeguro = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombreOrig);
$nombreFinal  = 'tema' . $temaId . '_' . time() . '_' . $nombreSeguro;
$rutaFisica   = $dirVideos . $nombreFinal;
$rutaWeb      = '/uploads/videos/' . $nombreFinal;

if (!rename($partPath, $rutaFisica)) {
    @unlink($partPath);
    $err = error_get_last();
    echo json_encode([
        'ok'          => false,
        'mensaje'     => 'No se pudo finalizar el archivo. Inténtalo de nuevo.',
        'diagnostico' => ['php_error' => $err['message'] ?? null],
    ]);
    exit;
}

// Registrar en base de datos
require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
$pdo = obtenerPDO();

$tamanoKb = (int)ceil($partSize / 1024);
$pdo->prepare(
    'INSERT INTO materiales (tema_id, tipo, nombre, ruta, tamano_kb, duracion_seg)
     VALUES (:tema_id, :tipo, :nombre, :ruta, :tamano_kb, :duracion_seg)'
)->execute([
    ':tema_id'      => $temaId,
    ':tipo'         => 'video',
    ':nombre'       => $nombreOrig,
    ':ruta'         => $rutaWeb,
    ':tamano_kb'    => $tamanoKb,
    ':duracion_seg' => $duracionSeg > 0 ? $duracionSeg : null,
]);
$materialId = (int)$pdo->lastInsertId();

registrar_log($pdo, 'material_subido',
    'Vídeo "' . $nombreOrig . '" subido al tema ID ' . $temaId .
    ' (stream, ' . round($partSize / 1024 / 1024) . ' MB)', 0);

echo json_encode([
    'ok'       => true,
    'material' => [
        'id'        => $materialId,
        'nombre'    => $nombreOrig,
        'ruta'      => $rutaWeb,
        'tamano_kb' => $tamanoKb,
        'tipo'      => 'video',
    ],
]);
