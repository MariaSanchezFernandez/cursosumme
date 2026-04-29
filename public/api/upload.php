<?php
// ─────────────────────────────────────────────────────────────
// api/upload.php  —  Subida de archivos por tema
// Método: POST multipart/form-data
// Campos: archivo (file), tema_id (int), tipo ('video'|'documento')
// Respuesta OK:  { "ok": true, "material": { id, nombre, ruta, tamano_kb, tipo } }
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$temaId   = (int)($_POST['tema_id'] ?? 0);
$tipo     = $_POST['tipo'] ?? '';
$duracion = isset($_POST['duracion']) ? (int)$_POST['duracion'] : null;

if (!$temaId || !in_array($tipo, ['video', 'documento'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Parámetros inválidos (tema_id y tipo requeridos)']);
    exit;
}

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $errores = [
        UPLOAD_ERR_INI_SIZE   => 'El archivo supera el límite del servidor',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el límite del formulario',
        UPLOAD_ERR_PARTIAL    => 'El archivo se subió de forma incompleta',
        UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo',
        UPLOAD_ERR_NO_TMP_DIR => 'No hay directorio temporal disponible',
        UPLOAD_ERR_CANT_WRITE => 'No se puede escribir en disco',
    ];
    $codigo = $_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['ok' => false, 'mensaje' => $errores[$codigo] ?? 'Error al subir el archivo']);
    exit;
}

// Extensiones permitidas por tipo
$ext_permitidas = [
    'video'     => ['mp4', 'webm', 'mov', 'avi', 'm4v', 'mkv'],
    'documento' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'mp3', 'm4a', 'wav', 'ogg', 'aac'],
];

$nombreOriginal = $_FILES['archivo']['name'];
$extension      = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

if (!in_array($extension, $ext_permitidas[$tipo])) {
    echo json_encode(['ok' => false, 'mensaje' => "Tipo de archivo no permitido (.{$extension})"]);
    exit;
}

// Tamaño máximo: 3.5 GB para video, 100 MB para documentos
// (el límite real lo impone upload_max_filesize/post_max_size en .user.ini)
$maxBytes = $tipo === 'video' ? 3584 * 1024 * 1024 : 100 * 1024 * 1024;
if ($_FILES['archivo']['size'] > $maxBytes) {
    $limite = $tipo === 'video' ? '3.5 GB' : '100 MB';
    echo json_encode(['ok' => false, 'mensaje' => "El archivo supera el límite de {$limite}"]);
    exit;
}

// Directorio de destino (relativo al document root)
$subdirectorio = $tipo === 'video' ? 'videos' : 'documentos';
$dirDestino    = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . "/uploads/{$subdirectorio}/";

if (!is_dir($dirDestino)) {
    mkdir($dirDestino, 0755, true);
}

// Nombre de archivo único
$nombreSeguro = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombreOriginal);
$nombreFinal  = 'tema' . $temaId . '_' . time() . '_' . $nombreSeguro;
$rutaFisica   = $dirDestino . $nombreFinal;
$rutaWeb      = "/uploads/{$subdirectorio}/{$nombreFinal}";

if (!@move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaFisica)) {
    $err  = error_get_last();
    $diag = [
        'destino_existe'  => is_dir($dirDestino),
        'destino_escribe' => is_writable($dirDestino),
        'tmp_existe'      => is_file($_FILES['archivo']['tmp_name']),
        'tmp_size'        => @filesize($_FILES['archivo']['tmp_name']),
        'php_error'       => $err['message'] ?? null,
        'disco_libre_mb'  => (int)floor(@disk_free_space($dirDestino) / 1024 / 1024),
    ];
    echo json_encode([
        'ok'          => false,
        'mensaje'     => 'No se pudo guardar el archivo en el servidor',
        'diagnostico' => $diag,
    ]);
    exit;
}

$tamanoKb = (int)ceil($_FILES['archivo']['size'] / 1024);

// Insertar en base de datos
require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
$pdo = obtenerPDO();

$stmt = $pdo->prepare(
    'INSERT INTO materiales (tema_id, tipo, nombre, ruta, tamano_kb)
     VALUES (:tema_id, :tipo, :nombre, :ruta, :tamano_kb)'
);
$stmt->execute([
    ':tema_id'   => $temaId,
    ':tipo'      => $tipo,
    ':nombre'    => $nombreOriginal,
    ':ruta'      => $rutaWeb,
    ':tamano_kb' => $tamanoKb,
]);

$materialId = $pdo->lastInsertId();

// Si es un vídeo y se proporcionó duración, actualizarla en el tema
if ($tipo === 'video' && $duracion !== null && $duracion > 0) {
    $stmtDur = $pdo->prepare('UPDATE temas SET duracion = :dur WHERE id = :id');
    $stmtDur->execute([':dur' => $duracion, ':id' => $temaId]);
}

$adminId = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : 0;
registrar_log($pdo, 'material_subido', ucfirst($tipo) . ' "' . $nombreOriginal . '" subido al tema ID ' . $temaId, $adminId);

echo json_encode([
    'ok'       => true,
    'material' => [
        'id'        => $materialId,
        'nombre'    => $nombreOriginal,
        'ruta'      => $rutaWeb,
        'tamano_kb' => $tamanoKb,
        'tipo'      => $tipo,
    ],
]);
