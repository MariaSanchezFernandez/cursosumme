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
$token      = isset($_GET['token'])       ? (string)$_GET['token']    : '';

if (!$materialId || !$usuarioId) { http_response_code(400); exit; }

require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();

// Verificación de identidad real: el cliente debe presentar el token
// emitido por /api/login.php. Sin token válido + no expirado, 401.
// Esto cierra el agujero de poder pasar usuario_id=1 (un admin
// conocido) y colarse para ver vídeos. El token también se acepta
// vía Authorization: Bearer <token> para llamadas de JS.
$auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? trim($_SERVER['HTTP_AUTHORIZATION']) : '';
if ($token === '' && stripos($auth, 'Bearer ') === 0) {
    $token = substr($auth, 7);
}
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(401); exit;
}

$authStmt = $pdo->prepare(
    'SELECT u.rol FROM usuarios u
     INNER JOIN sesiones s ON s.usuario_id = u.id
     WHERE u.id = :id AND s.token = :t AND s.expira_en > NOW()
     LIMIT 1'
);
$authStmt->execute([':id' => $usuarioId, ':t' => $token]);
$rol = $authStmt->fetchColumn();
if (!$rol) { http_response_code(401); exit; }

if ($rol === 'admin') {
    $stmt = $pdo->prepare('SELECT ruta, nombre FROM materiales WHERE id = ? AND tipo = "video"');
    $stmt->execute([$materialId]);
    $mat = $stmt->fetch();
} else {
    // Alumno: verificar matrícula en el curso. Si es alumna de Rocío y
    // tiene un bloqueo activo para ESTE tema, denegamos el acceso al
    // archivo aunque conociera la URL.
    $stmt = $pdo->prepare(
        'SELECT m.ruta, m.nombre FROM materiales m
         INNER JOIN temas t ON t.id = m.tema_id
         INNER JOIN usuarios_cursos uc ON uc.curso_id = t.curso_id
         INNER JOIN cursos c ON c.id = t.curso_id
         INNER JOIN usuarios u ON u.id = uc.usuario_id
         LEFT JOIN temas_bloqueos_alumno b
                ON b.usuario_id = uc.usuario_id AND b.tema_id = t.id
         WHERE m.id = :mid AND m.tipo = "video"
           AND uc.usuario_id = :uid AND c.activo = 1
           AND (u.es_alumna_rocio = 0 OR b.bloqueado_hasta IS NULL OR b.bloqueado_hasta <= NOW())'
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
while (ob_get_level()) {
    ob_end_clean();
}
ini_set('zlib.output_compression', '0');
ini_set('output_buffering', '0');
ini_set('implicit_flush', '1');

// Las descargas de cientos de MB tardan minutos: sin esto PHP corta
// por max_execution_time y el archivo llega truncado/corrupto. 0 = sin
// límite mientras el cliente siga conectado.
@set_time_limit(0);
ignore_user_abort(false);

header("Content-Type: $mime");
header('Content-Encoding: identity');
header('Accept-Ranges: bytes');
header('Cache-Control: no-store, no-cache');
header('X-Content-Type-Options: nosniff');

// Modo descarga: solo permitido para admin. Fuerza al navegador a
// guardar el archivo con su nombre original en lugar de reproducirlo
// en línea. Los alumnos NO pueden descargar aunque pasen ?descarga=1
// (la condición exige $rol === 'admin', validado por token).
if (!empty($_GET['descarga']) && $rol === 'admin') {
    // Saneamiento del nombre antes de meterlo en una cabecera HTTP:
    //  - quitar CR/LF/comillas/backslashes (response-splitting)
    //  - quitar separadores de ruta y secuencias ".." (path injection
    //    en el lado cliente al sugerir un nombre de archivo)
    //  - colapsar a un fallback seguro si queda vacío
    $bruto        = $mat['nombre'] ?? 'video.mp4';
    $sinControl   = preg_replace('/[\x00-\x1F\x7F"\\\\]/', '', $bruto);
    $sinSlash     = str_replace(['/', '\\'], '', $sinControl);
    $nombreSeguro = trim(str_replace('..', '', $sinSlash));
    if ($nombreSeguro === '') {
        $nombreSeguro = 'video.mp4';
    }

    // RFC 6266: filename para clientes legacy + filename* UTF-8
    $nombreUtf8 = rawurlencode($nombreSeguro);
    header("Content-Disposition: attachment; filename=\"$nombreSeguro\"; filename*=UTF-8''$nombreUtf8");

    // Solo registramos la descarga si NO es una petición Range:
    // los reproductores de algunos navegadores piden por chunks y
    // no queremos N entradas en el log por un solo "Descargar".
    if (!isset($_SERVER['HTTP_RANGE'])) {
        require_once __DIR__ . '/log-helper.php';
        registrar_log(
            $pdo,
            'video_descargado',
            'Descargado vídeo "' . $nombreSeguro . '" (material ID ' . $materialId . ')',
            $usuarioId,
        );
    }
}

// Tamaño de chunk para streaming. 256 KB es buen compromiso entre
// llamadas a fread (pocas) y consumo de memoria por petición (acotado).
$CHUNK = 256 * 1024;

// Soporte de range requests (necesario para seek en el reproductor y
// para que el navegador pueda reanudar descargas grandes).
if (isset($_SERVER['HTTP_RANGE'])) {
    [, $range]      = explode('=', $_SERVER['HTTP_RANGE'], 2);
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
    while ($remaining > 0 && !feof($fp) && !connection_aborted()) {
        $chunk = fread($fp, min($CHUNK, $remaining));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        @flush();
        $remaining -= strlen($chunk);
    }
    fclose($fp);
} else {
    // Descarga / streaming completos: NO usar readfile() porque para
    // archivos grandes (cientos de MB) tiende a buffer todo el contenido
    // en memoria y a chocar con max_execution_time / memory_limit, lo
    // que provoca archivos truncados ("descargado corrupto"). En su
    // lugar leemos en chunks y vaciamos buffers periódicamente.
    header("Content-Length: $size");
    $fp = fopen($rutaFisica, 'rb');
    if ($fp === false) { http_response_code(500); exit; }
    while (!feof($fp) && !connection_aborted()) {
        $chunk = fread($fp, $CHUNK);
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        @flush();
    }
    fclose($fp);
}
