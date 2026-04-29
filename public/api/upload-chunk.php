<?php
// ─────────────────────────────────────────────────────────────
// api/upload-chunk.php  —  Subida de vídeos por chunks
// Método: POST multipart/form-data
// Campos: upload_id (string), chunk_index (int), total_chunks (int),
//         tema_id (int), nombre_original (string), duracion (int|null),
//         chunk (file)
// Respuesta OK intermedia: { "ok": true, "recibido": chunk_index }
// Respuesta OK final:      { "ok": true, "material": {...} }
//
// Pensado para esquivar el límite de 1 GB del nginx de IONOS:
// el cliente trocea el archivo en partes < 1 GB y cada parte viaja
// en su propia petición. Al recibir la última, se concatenan en
// un solo archivo y se registra en BD como un material normal.
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$uploadId    = $_POST['upload_id']     ?? '';
$chunkIndex  = isset($_POST['chunk_index'])  ? (int)$_POST['chunk_index']  : -1;
$totalChunks = isset($_POST['total_chunks']) ? (int)$_POST['total_chunks'] : 0;
$temaId      = isset($_POST['tema_id'])      ? (int)$_POST['tema_id']      : 0;
$nombreOrig  = $_POST['nombre_original'] ?? '';
$duracion    = isset($_POST['duracion'])     ? (int)$_POST['duracion']     : 0;

// upload_id debe ser hex/alfanumérico (evitar path traversal)
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

if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Chunk no recibido correctamente']);
    exit;
}

$extension = strtolower(pathinfo($nombreOrig, PATHINFO_EXTENSION));
if (!in_array($extension, ['mp4', 'webm', 'mov', 'avi', 'm4v', 'mkv'])) {
    echo json_encode(['ok' => false, 'mensaje' => "Tipo de vídeo no permitido (.{$extension})"]);
    exit;
}

// Tamaño máximo total: 2 GB (~ PHP upload_max_filesize)
$MAX_TOTAL = 2048 * 1024 * 1024;

// Directorio temporal para los chunks de este upload
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$tmpRoot = $docRoot . '/uploads/.tmp-uploads';
$tmpDir  = $tmpRoot . '/' . $uploadId;

// Limpieza preventiva: en uploads previos abortados quedan dirs
// huérfanos en .tmp-uploads consumiendo cuota. Se borran los que
// llevan inactivos > 6 h (uploads grandes pueden tardar minutos,
// pero ninguno legítimo dura tantas horas).
if (is_dir($tmpRoot) && $chunkIndex === 0) {
    $umbral = time() - 6 * 3600;
    foreach (glob($tmpRoot . '/*', GLOB_ONLYDIR) ?: [] as $orfano) {
        if (@filemtime($orfano) > $umbral) {
            continue;
        }
        foreach (glob($orfano . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($orfano);
    }
}
if (!is_dir($tmpDir)) {
    if (!@mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
        $err = error_get_last();
        echo json_encode([
            'ok'          => false,
            'mensaje'     => 'No se pudo crear el directorio temporal',
            'diagnostico' => [
                'tmp_root_existe'  => is_dir($tmpRoot),
                'tmp_root_escribe' => is_writable($tmpRoot),
                'doc_root'         => $docRoot,
                'php_error'        => $err['message'] ?? null,
                'disco_libre_mb'   => (int)floor(@disk_free_space($docRoot) / 1024 / 1024),
            ],
        ]);
        exit;
    }
}

// Mover el chunk recibido a su posición
$chunkPath = $tmpDir . '/' . $chunkIndex . '.part';
if (!@move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkPath)) {
    $err = error_get_last();
    echo json_encode([
        'ok'          => false,
        'mensaje'     => 'No se pudo guardar el chunk',
        'diagnostico' => [
            'tmp_dir_existe'  => is_dir($tmpDir),
            'tmp_dir_escribe' => is_writable($tmpDir),
            'tmp_existe'      => is_file($_FILES['chunk']['tmp_name']),
            'tmp_size'        => @filesize($_FILES['chunk']['tmp_name']),
            'php_error'       => $err['message'] ?? null,
            'disco_libre_mb'  => (int)floor(@disk_free_space($tmpDir) / 1024 / 1024),
        ],
    ]);
    exit;
}

// Si no es el último chunk, responder y salir
if ($chunkIndex < $totalChunks - 1) {
    echo json_encode(['ok' => true, 'recibido' => $chunkIndex]);
    exit;
}

// ─── Último chunk: verificar que estén todos y ensamblar ───
for ($i = 0; $i < $totalChunks; $i++) {
    if (!file_exists($tmpDir . '/' . $i . '.part')) {
        echo json_encode(['ok' => false, 'mensaje' => "Falta el chunk {$i}, cancela y reintenta"]);
        exit;
    }
}

// Preparar ruta final
$dirDestino = $docRoot . '/uploads/videos/';
if (!is_dir($dirDestino) && !mkdir($dirDestino, 0755, true) && !is_dir($dirDestino)) {
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo crear el directorio de vídeos']);
    exit;
}
$nombreSeguro = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombreOrig);
$nombreFinal  = 'tema' . $temaId . '_' . time() . '_' . $nombreSeguro;
$rutaFisica   = $dirDestino . $nombreFinal;
$rutaWeb      = '/uploads/videos/' . $nombreFinal;

// Concatenar chunks en el archivo final
$out = fopen($rutaFisica, 'wb');
if (!$out) {
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo abrir el archivo final para escritura']);
    exit;
}
$totalBytes = 0;
for ($i = 0; $i < $totalChunks; $i++) {
    $part = $tmpDir . '/' . $i . '.part';
    $in   = fopen($part, 'rb');
    if (!$in) {
        fclose($out);
        @unlink($rutaFisica);
        echo json_encode(['ok' => false, 'mensaje' => "No se pudo leer el chunk {$i}"]);
        exit;
    }
    while (!feof($in)) {
        $buf = fread($in, 1024 * 1024);
        if ($buf === false) break;
        $totalBytes += strlen($buf);
        if ($totalBytes > $MAX_TOTAL) {
            fclose($in); fclose($out);
            @unlink($rutaFisica);
            borrarTmp($tmpDir);
            echo json_encode(['ok' => false, 'mensaje' => 'El vídeo ensamblado supera 2 GB']);
            exit;
        }
        fwrite($out, $buf);
    }
    fclose($in);
}
fclose($out);

// Limpieza de chunks temporales
borrarTmp($tmpDir);

// Registrar en BD
require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
$pdo = obtenerPDO();
$tamanoKb = (int)ceil($totalBytes / 1024);
$stmt = $pdo->prepare(
    'INSERT INTO materiales (tema_id, tipo, nombre, ruta, tamano_kb)
     VALUES (:tema_id, :tipo, :nombre, :ruta, :tamano_kb)'
);
$stmt->execute([
    ':tema_id'   => $temaId,
    ':tipo'      => 'video',
    ':nombre'    => $nombreOrig,
    ':ruta'      => $rutaWeb,
    ':tamano_kb' => $tamanoKb,
]);
$materialId = $pdo->lastInsertId();

if ($duracion > 0) {
    $stmtDur = $pdo->prepare('UPDATE temas SET duracion = :dur WHERE id = :id');
    $stmtDur->execute([':dur' => $duracion, ':id' => $temaId]);
}

$adminId = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : 0;
registrar_log($pdo, 'material_subido', 'Vídeo "' . $nombreOrig . '" subido al tema ID ' . $temaId . ' (chunked, ' . round($totalBytes / 1024 / 1024) . ' MB)', $adminId);

echo json_encode([
    'ok' => true,
    'material' => [
        'id'        => $materialId,
        'nombre'    => $nombreOrig,
        'ruta'      => $rutaWeb,
        'tamano_kb' => $tamanoKb,
        'tipo'      => 'video',
    ],
]);

function borrarTmp(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (glob($dir . '/*') as $f) @unlink($f);
    @rmdir($dir);
}
