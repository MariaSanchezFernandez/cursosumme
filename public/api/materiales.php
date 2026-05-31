<?php
// ─────────────────────────────────────────────────────────────
// api/materiales.php  —  Gestión de materiales de un tema
// GET    ?tema_id=X    → lista materiales del tema
// GET    ?reusables=1  → lista vídeos VdoCipher 'ready' ya usados (admin)
// POST   { tema_id, vdocipher_video_id } → reutiliza un vídeo existente (admin)
// DELETE ?id=X         → elimina material (archivo + registro BD)
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
$pdo  = obtenerPDO();
$user = requireAuth($pdo);

$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET ?reusables=1 — vídeos ya subidos para reutilizar (admin) ─
if ($metodo === 'GET' && isset($_GET['reusables'])) {
    if ($user['rol'] !== 'admin') { http_response_code(403); echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado']); exit; }
    // Un vídeo se identifica por su vdocipher_video_id. Puede estar usado en
    // varios temas; agrupamos para listarlo una sola vez. Mostramos el nombre
    // del primer material que lo usó y en cuántos temas se reutiliza ya.
    $stmt = $pdo->query(
        "SELECT vdocipher_video_id,
                SUBSTRING_INDEX(GROUP_CONCAT(nombre ORDER BY id ASC SEPARATOR 0x1f), 0x1f, 1) AS nombre,
                MAX(duracion_seg) AS duracion_seg,
                COUNT(*)          AS usos
         FROM materiales
         WHERE tipo = 'video'
           AND vdocipher_video_id IS NOT NULL
           AND vdo_status = 'ready'
         GROUP BY vdocipher_video_id
         ORDER BY nombre ASC"
    );
    echo json_encode(['ok' => true, 'videos' => $stmt->fetchAll()]);
    exit;
}

// ── GET ──────────────────────────────────────────────────────
if ($metodo === 'GET') {
    $temaId = isset($_GET['tema_id']) ? (int)$_GET['tema_id'] : 0;
    if (!$temaId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Falta tema_id']);
        exit;
    }
    // Si el tema está bloqueado y el usuario no es admin, no listamos
    // sus materiales: el alumno solo debe ver la cuenta atrás.
    if ($user['rol'] !== 'admin') {
        $stmtBh = $pdo->prepare('SELECT bloqueado_hasta FROM temas WHERE id = :id');
        $stmtBh->execute([':id' => $temaId]);
        $bh = $stmtBh->fetchColumn();
        if ($bh && strtotime($bh) > time()) {
            echo json_encode(['ok' => true, 'materiales' => [], 'bloqueado_hasta' => $bh]);
            exit;
        }
    }
    $stmt = $pdo->prepare(
        'SELECT id, tipo, nombre, ruta, tamano_kb, duracion_seg, vdocipher_video_id, vdo_status, subido_en
         FROM materiales WHERE tema_id = :tema_id ORDER BY orden ASC, subido_en ASC'
    );
    $stmt->execute([':tema_id' => $temaId]);
    echo json_encode(['ok' => true, 'materiales' => $stmt->fetchAll()]);
    exit;
}

// ── POST — reutilizar un vídeo ya existente en otro tema (admin) ─
if ($metodo === 'POST') {
    if ($user['rol'] !== 'admin') { http_response_code(403); echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado']); exit; }
    $body  = json_decode(file_get_contents('php://input'), true);
    $temaId = (int)($body['tema_id'] ?? 0);
    $vdoId  = trim($body['vdocipher_video_id'] ?? '');
    if (!$temaId || $vdoId === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Faltan tema_id y vdocipher_video_id']);
        exit;
    }
    // Seguridad: el vídeo debe existir ya en NUESTRA BD y estar 'ready'.
    // Así evitamos que se inyecten IDs arbitrarios de vídeos de otras cuentas.
    $src = $pdo->prepare(
        "SELECT nombre, duracion_seg FROM materiales
         WHERE vdocipher_video_id = :vid AND tipo = 'video' AND vdo_status = 'ready'
         ORDER BY id ASC LIMIT 1"
    );
    $src->execute([':vid' => $vdoId]);
    $origen = $src->fetch();
    if (!$origen) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'Vídeo no encontrado o aún no está listo']);
        exit;
    }
    // El tema destino debe existir.
    $chk = $pdo->prepare('SELECT id FROM temas WHERE id = :id');
    $chk->execute([':id' => $temaId]);
    if (!$chk->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'Tema no encontrado']);
        exit;
    }
    // Evitar duplicar el mismo vídeo dentro del mismo tema.
    $dup = $pdo->prepare(
        "SELECT id FROM materiales WHERE tema_id = :tema AND vdocipher_video_id = :vid LIMIT 1"
    );
    $dup->execute([':tema' => $temaId, ':vid' => $vdoId]);
    if ($dup->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'mensaje' => 'Ese vídeo ya está en este tema']);
        exit;
    }

    $ins = $pdo->prepare(
        'INSERT INTO materiales
           (tema_id, tipo, nombre, ruta, tamano_kb, duracion_seg, vdocipher_video_id, vdo_status)
         VALUES (:tema, :tipo, :nombre, NULL, 0, :dur, :vid, :status)'
    );
    $ins->execute([
        ':tema'   => $temaId,
        ':tipo'   => 'video',
        ':nombre' => $origen['nombre'],
        ':dur'    => $origen['duracion_seg'] !== null ? (int)$origen['duracion_seg'] : null,
        ':vid'    => $vdoId,
        ':status' => 'ready',
    ]);
    $nuevoId = (int)$pdo->lastInsertId();
    registrar_log($pdo, 'material_reutilizado',
        "Vídeo reutilizado \"{$origen['nombre']}\" (videoId={$vdoId}) en tema {$temaId}", $user['id'] ?? 0);

    echo json_encode(['ok' => true, 'material' => [
        'id'                 => $nuevoId,
        'tipo'               => 'video',
        'nombre'             => $origen['nombre'],
        'ruta'               => null,
        'tamano_kb'          => 0,
        'duracion_seg'       => $origen['duracion_seg'] !== null ? (int)$origen['duracion_seg'] : null,
        'vdocipher_video_id' => $vdoId,
        'vdo_status'         => 'ready',
    ]]);
    exit;
}

// ── PUT (renombrar / reordenar) ───────────────────────────────
if ($metodo === 'PUT') {
    if ($user['rol'] !== 'admin') { http_response_code(403); echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado']); exit; }
    $body = json_decode(file_get_contents('php://input'), true);

    // Reordenar: recibe { accion:'reordenar', ids:[1,2,3,...] }
    if (($body['accion'] ?? '') === 'reordenar') {
        $ids = array_values(array_filter(array_map('intval', $body['ids'] ?? [])));
        if (empty($ids)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'mensaje' => 'Falta ids']);
            exit;
        }
        $stmt = $pdo->prepare('UPDATE materiales SET orden=:orden WHERE id=:id');
        foreach ($ids as $pos => $id) {
            $stmt->execute([':orden' => $pos, ':id' => $id]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // Actualizar: recibe { id, nombre?, duracion_seg? }
    //   - nombre: string (renombrar)
    //   - duracion_seg: int|null (override manual; null = vaciar, 0 ignora)
    if (empty($body['id'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Falta el id']);
        exit;
    }

    $sets   = [];
    $params = [':id' => (int)$body['id']];
    if (array_key_exists('nombre', $body)) {
        $sets[] = 'nombre = :nombre';
        $params[':nombre'] = trim($body['nombre']);
    }
    if (array_key_exists('duracion_seg', $body)) {
        $sets[] = 'duracion_seg = :dur';
        // null permitido para borrar la duración. Un int <= 0 también limpia.
        $dur = $body['duracion_seg'];
        if ($dur === null || (is_numeric($dur) && (int)$dur <= 0)) {
            $params[':dur'] = null;
        } else {
            $params[':dur'] = (int)$dur;
        }
    }
    if (empty($sets)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'No hay campos que actualizar']);
        exit;
    }
    $pdo->prepare('UPDATE materiales SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    echo json_encode(['ok' => true]);
    exit;
}

// ── DELETE ───────────────────────────────────────────────────
if ($metodo === 'DELETE') {
    if ($user['rol'] !== 'admin') { http_response_code(403); echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado']); exit; }
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        $body = json_decode(file_get_contents('php://input'), true);
        $id = (int)($body['id'] ?? 0);
    }
    if (!$id) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Falta el id del material']);
        exit;
    }
    // Obtener datos para log y limpieza
    $stmt = $pdo->prepare('SELECT nombre, tipo, ruta, vdocipher_video_id FROM materiales WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $mat = $stmt->fetch();
    if ($mat) {
        // Vídeo local: borrar archivo físico
        if ($mat['ruta']) {
            $rutaFisica = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $mat['ruta'];
            if (file_exists($rutaFisica)) { @unlink($rutaFisica); }
        }
        // Vídeo VdoCipher: borrar del CDN SOLO si ningún otro material
        // reutiliza el mismo vídeo. Si está reutilizado en otro tema,
        // borrarlo del CDN dejaría a los demás materiales sin vídeo.
        if ($mat['vdocipher_video_id']) {
            $usos = $pdo->prepare(
                'SELECT COUNT(*) FROM materiales WHERE vdocipher_video_id = :vid AND id != :id'
            );
            $usos->execute([':vid' => $mat['vdocipher_video_id'], ':id' => $id]);
            $reutilizado = (int)$usos->fetchColumn() > 0;

            $apiKey = defined('VDOCIPHER_API_KEY') ? VDOCIPHER_API_KEY : '';
            if (!$reutilizado && $apiKey) {
                $vid = rawurlencode($mat['vdocipher_video_id']);
                $ch  = curl_init("https://dev.vdocipher.com/api/videos?videos={$vid}");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST  => 'DELETE',
                    CURLOPT_HTTPHEADER     => ['Authorization: Apisecret ' . $apiKey],
                    CURLOPT_TIMEOUT        => 10,
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                curl_exec($ch);
                curl_close($ch);
            }
        }
    }

    $stmt = $pdo->prepare('DELETE FROM materiales WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($mat) {
        registrar_log($pdo, 'material_eliminado', ucfirst($mat['tipo']) . ' "' . $mat['nombre'] . '" eliminado', 0);
    }
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
