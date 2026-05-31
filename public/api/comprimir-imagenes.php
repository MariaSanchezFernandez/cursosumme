<?php
// ─────────────────────────────────────────────────────────────
// api/comprimir-imagenes.php  —  One-shot admin
//
// POST  → recorre /uploads/imagenes/, redimensiona a max 1200px y
//          recodea a WebP calidad 82 cualquier imagen > 200 KB o
//          cuyo ancho supere 1200px. Actualiza cursos.imagen en BD
//          si cambia el nombre del archivo y borra el original.
//
// Devuelve un resumen con archivos procesados, bytes ahorrados y
// posibles errores. Es idempotente: imágenes ya en .webp y de
// tamaño/ancho razonables se saltan.
//
// Pensado para correrlo una vez tras desplegar la mejora de subida.
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

if (!extension_loaded('gd') || !function_exists('imagewebp')) {
    echo json_encode(['ok' => false, 'mensaje' => 'GD/WebP no disponible en este servidor']);
    exit;
}

@set_time_limit(300);

$dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/uploads/imagenes/';
if (!is_dir($dir)) {
    echo json_encode(['ok' => true, 'procesadas' => 0, 'mensaje' => 'Carpeta no existe']);
    exit;
}

$MAX_ANCHO     = 1200;
$CALIDAD_WEBP  = 82;
$UMBRAL_BYTES  = 200 * 1024; // 200 KB

$archivos = scandir($dir);
$resumen = [
    'revisadas'    => 0,
    'optimizadas'  => 0,
    'saltadas'     => 0,
    'errores'      => 0,
    'bytes_antes'  => 0,
    'bytes_despues'=> 0,
    'detalle'      => [],
];

foreach ($archivos as $f) {
    if ($f === '.' || $f === '..') continue;
    $ruta = $dir . $f;
    if (!is_file($ruta)) continue;

    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) continue;

    $resumen['revisadas']++;
    $tamOrig = filesize($ruta);

    // Saltar si ya es webp y pequeño y con dimensión razonable
    $info = @getimagesize($ruta);
    if (!$info) {
        $resumen['errores']++;
        $resumen['detalle'][] = ['archivo' => $f, 'error' => 'getimagesize fallo'];
        continue;
    }
    $w = $info[0]; $h = $info[1];

    $necesitaResize  = $w > $MAX_ANCHO;
    $necesitaRecomp  = $tamOrig > $UMBRAL_BYTES;
    if ($ext === 'webp' && !$necesitaResize && !$necesitaRecomp) {
        $resumen['saltadas']++;
        continue;
    }

    // Cargar
    $src = null;
    switch ($ext) {
        case 'jpg': case 'jpeg': $src = @imagecreatefromjpeg($ruta); break;
        case 'png':              $src = @imagecreatefrompng($ruta);  break;
        case 'webp':             $src = @imagecreatefromwebp($ruta); break;
    }
    if (!$src) {
        $resumen['errores']++;
        $resumen['detalle'][] = ['archivo' => $f, 'error' => 'no se pudo cargar'];
        continue;
    }

    if ($w > $MAX_ANCHO) {
        $nuevoW = $MAX_ANCHO;
        $nuevoH = (int)round($h * ($MAX_ANCHO / $w));
        $dst = imagecreatetruecolor($nuevoW, $nuevoH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nuevoW, $nuevoH, $w, $h);
        imagedestroy($src);
        $src = $dst;
    }

    // Generar nombre webp. Si el archivo YA es .webp, lo sobrescribimos
    // en el sitio. Si no, generamos otro nombre y borramos el viejo.
    $base = pathinfo($f, PATHINFO_FILENAME);
    $nuevoNombre = $base . '.webp';
    $rutaNueva   = $dir . $nuevoNombre;

    // Escribir a archivo temporal y mover sólo si todo va bien
    $tmpOut = $rutaNueva . '.tmp';
    if (!@imagewebp($src, $tmpOut, $CALIDAD_WEBP)) {
        imagedestroy($src);
        @unlink($tmpOut);
        $resumen['errores']++;
        $resumen['detalle'][] = ['archivo' => $f, 'error' => 'imagewebp fallo'];
        continue;
    }
    imagedestroy($src);

    // Si el resultado pesa más que el original (raro), tirar resultado y saltar
    $tamNuevo = filesize($tmpOut);
    if ($tamNuevo >= $tamOrig && $ext === 'webp' && !$necesitaResize) {
        @unlink($tmpOut);
        $resumen['saltadas']++;
        continue;
    }

    // Confirmar movimiento
    if (!@rename($tmpOut, $rutaNueva)) {
        @unlink($tmpOut);
        $resumen['errores']++;
        $resumen['detalle'][] = ['archivo' => $f, 'error' => 'rename fallo'];
        continue;
    }

    // Si el original tenía otra extensión, borrarlo + actualizar BD
    if ($ext !== 'webp') {
        @unlink($ruta);
        $rutaWebVieja = '/uploads/imagenes/' . $f;
        $rutaWebNueva = '/uploads/imagenes/' . $nuevoNombre;
        $st = $pdo->prepare('UPDATE cursos SET imagen = :nueva WHERE imagen = :vieja');
        $st->execute([':nueva' => $rutaWebNueva, ':vieja' => $rutaWebVieja]);
    }

    $resumen['optimizadas']++;
    $resumen['bytes_antes']   += $tamOrig;
    $resumen['bytes_despues'] += $tamNuevo;
    $resumen['detalle'][] = [
        'archivo'     => $f,
        'archivo_nuevo' => $nuevoNombre,
        'kb_antes'    => (int)round($tamOrig / 1024),
        'kb_despues'  => (int)round($tamNuevo / 1024),
    ];
}

$resumen['kb_antes']   = (int)round($resumen['bytes_antes']   / 1024);
$resumen['kb_despues'] = (int)round($resumen['bytes_despues'] / 1024);
$resumen['kb_ahorrados'] = $resumen['kb_antes'] - $resumen['kb_despues'];
$resumen['ok'] = true;

echo json_encode($resumen);
