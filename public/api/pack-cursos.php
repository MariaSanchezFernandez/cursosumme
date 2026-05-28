<?php
// ─────────────────────────────────────────────────────────────
// api/pack-cursos.php  —  Gestión de enlaces N:N pack ↔ cursos
// GET  ?pack_id=X     → devuelve [{curso_id}, …]
// PUT  body: {pack_id, curso_ids: number[]}
//                     → reemplaza atómicamente los enlaces del pack
// DELETE ?pack_id=X&curso_id=Y → quita un enlace concreto
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/db-config.php';

$pdo    = obtenerPDO();
$metodo = $_SERVER['REQUEST_METHOD'];

// GET es público (lo usa la página de precios para mostrar qué incluye cada pack)
if ($metodo === 'GET') {
    $packId = (int)($_GET['pack_id'] ?? 0);
    if (!$packId) {
        // Sin pack_id → devolver TODOS los enlaces, agrupados por pack
        $rows = $pdo->query(
            'SELECT pack_id, curso_id FROM pack_cursos ORDER BY pack_id, curso_id'
        )->fetchAll();
        echo json_encode(['ok' => true, 'enlaces' => $rows]);
        exit;
    }
    $stmt = $pdo->prepare('SELECT curso_id FROM pack_cursos WHERE pack_id = :p ORDER BY curso_id');
    $stmt->execute([':p' => $packId]);
    $cursoIds = array_map('intval', array_column($stmt->fetchAll(), 'curso_id'));
    echo json_encode(['ok' => true, 'pack_id' => $packId, 'curso_ids' => $cursoIds]);
    exit;
}

// El resto requiere admin
if (!empty($_GET['token']) && empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $_GET['token'];
}
requireAdmin($pdo);

$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($metodo === 'PUT') {
    $packId    = (int)($body['pack_id'] ?? 0);
    $cursoIds  = $body['curso_ids'] ?? [];
    if (!$packId || !is_array($cursoIds)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Parámetros inválidos']);
        exit;
    }
    // Verificar que el pack existe
    $chk = $pdo->prepare('SELECT 1 FROM packs WHERE id = :id LIMIT 1');
    $chk->execute([':id' => $packId]);
    if (!$chk->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'Pack no encontrado']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM pack_cursos WHERE pack_id = :p')->execute([':p' => $packId]);
        if (!empty($cursoIds)) {
            $ins = $pdo->prepare('INSERT INTO pack_cursos (pack_id, curso_id) VALUES (:p, :c)');
            foreach ($cursoIds as $cid) {
                $ins->execute([':p' => $packId, ':c' => (int)$cid]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['ok' => false, 'mensaje' => 'Error guardando enlaces']);
        error_log('pack-cursos PUT: ' . $e->getMessage());
        exit;
    }
    echo json_encode(['ok' => true, 'pack_id' => $packId, 'total' => count($cursoIds)]);
    exit;
}

if ($metodo === 'DELETE') {
    $packId   = (int)($_GET['pack_id']   ?? 0);
    $cursoId  = (int)($_GET['curso_id']  ?? 0);
    if (!$packId || !$cursoId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'pack_id y curso_id requeridos']);
        exit;
    }
    $pdo->prepare('DELETE FROM pack_cursos WHERE pack_id = :p AND curso_id = :c')
        ->execute([':p' => $packId, ':c' => $cursoId]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
