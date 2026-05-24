<?php
// ─────────────────────────────────────────────────────────────
// api/materiales.php  —  Gestión de materiales de un tema
// GET    ?tema_id=X  → lista materiales del tema
// DELETE ?id=X       → elimina material (archivo + registro BD)
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
$pdo  = obtenerPDO();
$user = requireAuth($pdo);

$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────────────────────
if ($metodo === 'GET') {
    $temaId = isset($_GET['tema_id']) ? (int)$_GET['tema_id'] : 0;
    if (!$temaId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Falta tema_id']);
        exit;
    }
    $stmt = $pdo->prepare(
        'SELECT id, tipo, nombre, ruta, tamano_kb, duracion_seg, vdocipher_video_id, vdo_status, subido_en
         FROM materiales WHERE tema_id = :tema_id ORDER BY orden ASC, subido_en ASC'
    );
    $stmt->execute([':tema_id' => $temaId]);
    echo json_encode(['ok' => true, 'materiales' => $stmt->fetchAll()]);
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
        // Vídeo VdoCipher: borrar del CDN
        if ($mat['vdocipher_video_id']) {
            $apiKey = defined('VDOCIPHER_API_KEY') ? VDOCIPHER_API_KEY : '';
            if ($apiKey) {
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
