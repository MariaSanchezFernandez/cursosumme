<?php
// ─────────────────────────────────────────────────────────────
// api/video.php  —  Proxy seguro para servir vídeos
// GET ?material_id=X&usuario_id=Y
// Verifica que el usuario tiene acceso al curso antes de servir
// ─────────────────────────────────────────────────────────────

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$materialId = isset($_GET['material_id']) ? (int)$_GET['material_id'] : 0;
$usuarioId  = isset($_GET['usuario_id'])  ? (int)$_GET['usuario_id']  : 0;

if (!$materialId || !$usuarioId) { http_response_code(400); exit; }

require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();

// Si quien pide el vídeo es admin, le dejamos ver cualquier vídeo (para
// previsualizar materiales subidos sin necesidad de estar matriculado).
$rolStmt = $pdo->prepare('SELECT rol FROM usuarios WHERE id = ?');
$rolStmt->execute([$usuarioId]);
$rol = $rolStmt->fetchColumn();

if ($rol === 'admin') {
    $stmt = $pdo->prepare('SELECT ruta FROM materiales WHERE id = ? AND tipo = "video"');
    $stmt->execute([$materialId]);
    $mat = $stmt->fetch();
} else {
    // Alumno: verificar que está matriculado en el curso del material
    $stmt = $pdo->prepare(
        'SELECT m.ruta FROM materiales m
         INNER JOIN temas t ON t.id = m.tema_id
         INNER JOIN usuarios_cursos uc ON uc.curso_id = t.curso_id
         INNER JOIN cursos c ON c.id = t.curso_id
         WHERE m.id = :mid AND m.tipo = "video"
           AND uc.usuario_id = :uid AND c.activo = 1'
    );
    $stmt->execute([':mid' => $materialId, ':uid' => $usuarioId]);
    $mat = $stmt->fetch();
}

if (!$mat) { http_response_code(403); exit; }

$rutaFisica = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $mat['ruta'];
if (!file_exists($rutaFisica)) { http_response_code(404); exit; }

$size = filesize($rutaFisica);
$mime = mime_content_type($rutaFisica) ?: 'video/mp4';

// Desactivar compresión de salida para que Content-Length sea correcto
// (sin esto el navegador no puede determinar la duración del vídeo)
if (ob_get_level()) ob_end_clean();
ini_set('zlib.output_compression', '0');

header("Content-Type: $mime");
header('Content-Encoding: identity');
header('Accept-Ranges: bytes');
header('Cache-Control: no-store, no-cache');
header('X-Content-Type-Options: nosniff');

// Soporte de range requests (necesario para seek en el reproductor)
if (isset($_SERVER['HTTP_RANGE'])) {
    [$unit, $range] = explode('=', $_SERVER['HTTP_RANGE'], 2);
    [$start, $end]  = array_pad(explode('-', $range, 2), 2, '');
    $start = (int)$start;
    $end   = ($end !== '') ? (int)$end : $size - 1;
    $end   = min($end, $size - 1);
    $length = $end - $start + 1;

    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
    header("Content-Length: $length");

    $fp = fopen($rutaFisica, 'rb');
    fseek($fp, $start);
    $remaining = $length;
    while ($remaining > 0 && !feof($fp)) {
        $chunk = fread($fp, min(8192, $remaining));
        echo $chunk;
        $remaining -= strlen($chunk);
    }
    fclose($fp);
} else {
    header("Content-Length: $size");
    readfile($rutaFisica);
}
