<?php
// ─────────────────────────────────────────────────────────────
// api/upload.php  —  Subida de documentos y materiales por tema (raw stream)
//
// Parámetros en query string:
//   tipo ('documento' | 'video'), tema_id, nombre_original
//
// Cuerpo de la petición: bytes crudos del archivo
//   (Content-Type: application/octet-stream)
//
// El archivo se escribe directamente al destino sin pasar por /tmp.
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$temaId      = isset($_GET['tema_id'])        ? (int)$_GET['tema_id']       : 0;
$tipo        = $_GET['tipo']                   ?? '';
$nombreOrig  = $_GET['nombre_original']        ?? '';
$duracionSeg = isset($_GET['duracion_seg'])    ? (int)$_GET['duracion_seg']  : 0;

if (!$temaId || !in_array($tipo, ['video', 'documento']) || $nombreOrig === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Parámetros inválidos (tema_id, tipo y nombre_original requeridos)']);
    exit;
}

// Extensiones permitidas por tipo
$ext_permitidas = [
    'video'     => ['mp4', 'webm', 'mov', 'avi', 'm4v', 'mkv'],
    'documento' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'mp3', 'm4a', 'wav', 'ogg', 'aac'],
];

$extension = strtolower(pathinfo($nombreOrig, PATHINFO_EXTENSION));
if (!in_array($extension, $ext_permitidas[$tipo])) {
    echo json_encode(['ok' => false, 'mensaje' => "Tipo de archivo no permitido (.{$extension})"]);
    exit;
}

$maxBytes      = $tipo === 'video' ? 3584 * 1024 * 1024 : 500 * 1024 * 1024;
$subdirectorio = $tipo === 'video' ? 'videos' : 'documentos';
$docRoot       = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$dirDestino    = $docRoot . "/uploads/{$subdirectorio}/";

if (!is_dir($dirDestino) && !mkdir($dirDestino, 0755, true) && !is_dir($dirDestino)) {
    echo json_encode([
        'ok'          => false,
        'mensaje'     => 'No se pudo crear el directorio de destino',
        'diagnostico' => ['disco_libre_mb' => (int)floor(@disk_free_space($docRoot . '/uploads/') / 1024 / 1024)],
    ]);
    exit;
}

$nombreSeguro = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombreOrig);
$nombreFinal  = 'tema' . $temaId . '_' . time() . '_' . $nombreSeguro;
$rutaFisica   = $dirDestino . $nombreFinal;
$rutaWeb      = "/uploads/{$subdirectorio}/{$nombreFinal}";

// Escribir directamente desde el cuerpo de la petición (sin pasar por /tmp)
$src = fopen('php://input', 'rb');
$dst = @fopen($rutaFisica, 'wb');
if (!$dst) {
    fclose($src);
    $err = error_get_last();
    echo json_encode([
        'ok'          => false,
        'mensaje'     => 'No se pudo crear el archivo en el servidor',
        'diagnostico' => [
            'destino_existe'  => is_dir($dirDestino),
            'destino_escribe' => is_writable($dirDestino),
            'php_error'       => $err['message'] ?? null,
            'disco_libre_mb'  => (int)floor(@disk_free_space($dirDestino) / 1024 / 1024),
        ],
    ]);
    exit;
}

$totalBytes     = 0;
$limiteExcedido = false;
$errorEscritura = false;
while (!feof($src)) {
    $buf = fread($src, 1024 * 1024);
    if ($buf === false || $buf === '') break;
    $totalBytes += strlen($buf);
    if ($totalBytes > $maxBytes) {
        $limiteExcedido = true;
        break;
    }
    if (fwrite($dst, $buf) === false) {
        $errorEscritura = true;
        break;
    }
}
fclose($src);
fclose($dst);

if ($limiteExcedido) {
    @unlink($rutaFisica);
    $limite = $tipo === 'video' ? '3.5 GB' : '100 MB';
    echo json_encode(['ok' => false, 'mensaje' => "El archivo supera el límite de {$limite}"]);
    exit;
}

if ($errorEscritura || $totalBytes === 0) {
    @unlink($rutaFisica);
    $err = error_get_last();
    echo json_encode([
        'ok'          => false,
        'mensaje'     => 'No se pudo guardar el archivo en el servidor',
        'diagnostico' => [
            'destino_existe'  => is_dir($dirDestino),
            'destino_escribe' => is_writable($dirDestino),
            'php_error'       => $err['message'] ?? null,
            'disco_libre_mb'  => (int)floor(@disk_free_space($dirDestino) / 1024 / 1024),
        ],
    ]);
    exit;
}

$tamanoKb = (int)ceil($totalBytes / 1024);

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
$pdo = obtenerPDO();

$durSegPersistir = ($tipo === 'video' && $duracionSeg > 0) ? $duracionSeg : null;
$pdo->prepare(
    'INSERT INTO materiales (tema_id, tipo, nombre, ruta, tamano_kb, duracion_seg)
     VALUES (:tema_id, :tipo, :nombre, :ruta, :tamano_kb, :duracion_seg)'
)->execute([
    ':tema_id'      => $temaId,
    ':tipo'         => $tipo,
    ':nombre'       => $nombreOrig,
    ':ruta'         => $rutaWeb,
    ':tamano_kb'    => $tamanoKb,
    ':duracion_seg' => $durSegPersistir,
]);
$materialId = (int)$pdo->lastInsertId();

registrar_log($pdo, 'material_subido',
    ucfirst($tipo) . ' "' . $nombreOrig . '" subido al tema ID ' . $temaId .
    ' (stream, ' . round($totalBytes / 1024 / 1024, 1) . ' MB)', 0);

echo json_encode([
    'ok'       => true,
    'material' => [
        'id'        => $materialId,
        'nombre'    => $nombreOrig,
        'ruta'      => $rutaWeb,
        'tamano_kb' => $tamanoKb,
        'tipo'      => $tipo,
    ],
]);
