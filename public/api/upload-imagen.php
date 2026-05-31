<?php
// ─────────────────────────────────────────────────────────────
// api/upload-imagen.php  —  Sube imagen de portada de un curso
// POST multipart/form-data: archivo (file jpg/jpeg/png/webp)
// Respuesta OK: { "ok": true, "ruta": "/uploads/imagenes/..." }
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();
requireAdmin($pdo);

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
    @mkdir($dirDestino, 0755, true);
}

// Optimización: si GD está disponible, redimensionamos a 1200px máx de
// ancho y reencodeamos como WebP calidad 82. Esto reduce las portadas
// de varios MB a típicamente 60–200 KB sin pérdida visible. Si GD no
// estuviera disponible o el archivo no se pudiera leer, hacemos fallback
// al move_uploaded_file original para no romper la subida.
$MAX_ANCHO = 1200;
$CALIDAD_WEBP = 82;

$usarGd = extension_loaded('gd') && function_exists('imagewebp');
$compresionAplicada = false;
$origenSize = (int)$_FILES['archivo']['size'];

if ($usarGd) {
    $nombreFinal = 'portada_' . time() . '_' . bin2hex(random_bytes(4)) . '.webp';
    $rutaFisica  = $dirDestino . $nombreFinal;
    $rutaWeb     = '/uploads/imagenes/' . $nombreFinal;

    $tmp = $_FILES['archivo']['tmp_name'];
    $src = null;
    switch ($extension) {
        case 'jpg': case 'jpeg': $src = @imagecreatefromjpeg($tmp); break;
        case 'png':              $src = @imagecreatefrompng($tmp);  break;
        case 'webp':             $src = @imagecreatefromwebp($tmp); break;
    }

    if ($src) {
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w > $MAX_ANCHO) {
            $nuevoW = $MAX_ANCHO;
            $nuevoH = (int)round($h * ($MAX_ANCHO / $w));
            $dst = imagecreatetruecolor($nuevoW, $nuevoH);
            // Mantener transparencia para PNG
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nuevoW, $nuevoH, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }
        if (@imagewebp($src, $rutaFisica, $CALIDAD_WEBP)) {
            $compresionAplicada = true;
        }
        imagedestroy($src);
    }
}

if (!$compresionAplicada) {
    // Fallback: guardar original con su extensión
    $nombreFinal = 'portada_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $rutaFisica  = $dirDestino . $nombreFinal;
    $rutaWeb     = '/uploads/imagenes/' . $nombreFinal;

    if (!@move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaFisica)) {
        $err  = error_get_last();
        $diag = [
            'destino_existe'  => is_dir($dirDestino),
            'destino_escribe' => is_writable($dirDestino),
            'tmp_existe'      => is_file($_FILES['archivo']['tmp_name']),
            'tmp_size'        => @filesize($_FILES['archivo']['tmp_name']),
            'php_error'       => $err['message'] ?? null,
            'gd_disponible'   => $usarGd,
        ];
        echo json_encode([
            'ok'         => false,
            'mensaje'    => 'No se pudo guardar la imagen en el servidor',
            'diagnostico' => $diag,
        ]);
        exit;
    }
}

echo json_encode([
    'ok' => true,
    'ruta' => $rutaWeb,
    'optimizada' => $compresionAplicada,
    'tamano_original_kb' => (int)round($origenSize / 1024),
    'tamano_final_kb'    => (int)round(@filesize($rutaFisica) / 1024),
]);
