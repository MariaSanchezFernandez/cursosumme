<?php
// ─────────────────────────────────────────────────────────────
// api/upload-imagen.php  —  Sube imagen de portada de un curso
// POST multipart/form-data: archivo (file jpg/jpeg/png/webp)
// Respuesta OK: { "ok": true, "ruta": "/uploads/imagenes/..." }
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

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $errores = [
        UPLOAD_ERR_INI_SIZE   => 'El archivo supera el límite del servidor',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el límite del formulario',
        UPLOAD_ERR_PARTIAL    => 'Subida incompleta',
        UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo',
        UPLOAD_ERR_NO_TMP_DIR => 'No hay directorio temporal',
        UPLOAD_ERR_CANT_WRITE => 'No se puede escribir en disco',
    ];
    $codigo = $_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['ok' => false, 'mensaje' => $errores[$codigo] ?? 'Error al subir el archivo']);
    exit;
}

$ext_permitidas = ['jpg', 'jpeg', 'png', 'webp'];
$nombreOriginal = $_FILES['archivo']['name'];
$extension      = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

if (!in_array($extension, $ext_permitidas)) {
    echo json_encode(['ok' => false, 'mensaje' => "Formato no permitido (.{$extension}). Usa JPG, PNG o WEBP."]);
    exit;
}

// Máx 5 MB
if ($_FILES['archivo']['size'] > 5 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'mensaje' => 'La imagen supera el límite de 5 MB']);
    exit;
}

$dirDestino = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/uploads/imagenes/';
if (!is_dir($dirDestino)) {
    mkdir($dirDestino, 0755, true);
}

$nombreFinal = 'portada_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
$rutaFisica  = $dirDestino . $nombreFinal;
$rutaWeb     = '/uploads/imagenes/' . $nombreFinal;

if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaFisica)) {
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo guardar la imagen en el servidor']);
    exit;
}

echo json_encode(['ok' => true, 'ruta' => $rutaWeb]);
