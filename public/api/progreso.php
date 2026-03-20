<?php
// ─────────────────────────────────────────────────────────────
// api/progreso.php
// GET  ?usuario_id=X           → {ok, vistos:[tema_id, ...]}
// POST {usuario_id, tema_id}   → {ok}
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();

// Auto-migración
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS progresos (
        usuario_id INT NOT NULL,
        tema_id    INT NOT NULL,
        visto_en   DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (usuario_id, tema_id)
    )");
} catch (\Throwable $e) {}

// ── GET ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $uid = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
    if (!$uid) { http_response_code(400); echo json_encode(['ok'=>false,'mensaje'=>'Falta usuario_id']); exit; }

    $stmt = $pdo->prepare('SELECT tema_id FROM progresos WHERE usuario_id = :uid');
    $stmt->execute([':uid' => $uid]);
    $vistos = array_column($stmt->fetchAll(), 'tema_id');

    echo json_encode(['ok' => true, 'vistos' => array_map('intval', $vistos)]);
    exit;
}

// ── POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true);
    $uid    = isset($body['usuario_id']) ? (int)$body['usuario_id'] : 0;
    $temaId = isset($body['tema_id'])    ? (int)$body['tema_id']    : 0;

    if (!$uid || !$temaId) { http_response_code(400); echo json_encode(['ok'=>false,'mensaje'=>'Faltan datos']); exit; }

    $stmt = $pdo->prepare('INSERT INTO progresos (usuario_id, tema_id) VALUES (:uid, :tid)
                           ON DUPLICATE KEY UPDATE visto_en = CURRENT_TIMESTAMP');
    $stmt->execute([':uid' => $uid, ':tid' => $temaId]);

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
