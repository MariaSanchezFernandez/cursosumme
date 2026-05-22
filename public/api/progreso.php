<?php
// ─────────────────────────────────────────────────────────────
// api/progreso.php
// GET  ?usuario_id=X                          → {ok, vistos:[tema_id,...], materiales_vistos:[material_id,...]}
// POST {usuario_id, material_id, tema_id}     → {ok, tema_completado, tema_id}
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
$pdo  = obtenerPDO();
$user = requireAuth($pdo);

// ── GET ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $uid = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
    if (!$uid) { http_response_code(400); echo json_encode(['ok'=>false,'mensaje'=>'Falta usuario_id']); exit; }
    if ($uid !== (int)$user['id'] && $user['rol'] !== 'admin') {
        http_response_code(403); echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado']); exit;
    }

    $stmt = $pdo->prepare('SELECT tema_id FROM progresos WHERE usuario_id = :uid');
    $stmt->execute([':uid' => $uid]);
    $vistos = array_map('intval', array_column($stmt->fetchAll(), 'tema_id'));

    $stmt2 = $pdo->prepare('SELECT material_id FROM progresos_materiales WHERE usuario_id = :uid');
    $stmt2->execute([':uid' => $uid]);
    $materialesVistos = array_map('intval', array_column($stmt2->fetchAll(), 'material_id'));

    echo json_encode(['ok' => true, 'vistos' => $vistos, 'materiales_vistos' => $materialesVistos]);
    exit;
}

// ── POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit($pdo, 'progreso_post', 60);
    $body       = json_decode(file_get_contents('php://input'), true);
    $uid        = isset($body['usuario_id'])  ? (int)$body['usuario_id']  : 0;
    $materialId = isset($body['material_id']) ? (int)$body['material_id'] : 0;
    $temaId     = isset($body['tema_id'])     ? (int)$body['tema_id']     : 0;

    if (!$uid || !$materialId || !$temaId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Faltan datos']);
        exit;
    }
    if ($uid !== (int)$user['id'] && $user['rol'] !== 'admin') {
        http_response_code(403); echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado']); exit;
    }

    // Registrar vídeo visto
    $pdo->prepare('INSERT INTO progresos_materiales (usuario_id, material_id) VALUES (:uid, :mid)
                   ON DUPLICATE KEY UPDATE visto_en = CURRENT_TIMESTAMP')
        ->execute([':uid' => $uid, ':mid' => $materialId]);

    // Comprobar si todos los vídeos del tema están vistos
    $stTotal = $pdo->prepare("SELECT COUNT(*) FROM materiales WHERE tema_id = :tid AND tipo = 'video'");
    $stTotal->execute([':tid' => $temaId]);
    $totalVideos = (int)$stTotal->fetchColumn();

    $stVistos = $pdo->prepare(
        "SELECT COUNT(*) FROM progresos_materiales pm
         INNER JOIN materiales m ON m.id = pm.material_id
         WHERE pm.usuario_id = :uid AND m.tema_id = :tid AND m.tipo = 'video'"
    );
    $stVistos->execute([':uid' => $uid, ':tid' => $temaId]);
    $visitedVideos = (int)$stVistos->fetchColumn();

    $temaCompletado = $totalVideos > 0 && $visitedVideos >= $totalVideos;

    if ($temaCompletado) {
        $pdo->prepare('INSERT INTO progresos (usuario_id, tema_id) VALUES (:uid, :tid)
                       ON DUPLICATE KEY UPDATE visto_en = CURRENT_TIMESTAMP')
            ->execute([':uid' => $uid, ':tid' => $temaId]);
    }

    echo json_encode(['ok' => true, 'tema_completado' => $temaCompletado, 'tema_id' => $temaId]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
