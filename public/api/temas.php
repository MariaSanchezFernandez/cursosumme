<?php
// ─────────────────────────────────────────────────────────────
// api/temas.php  —  CRUD de temas de un curso
// GET    ?curso_id=X  → lista temas con número de materiales
// POST              → crea tema  { curso_id, titulo, duracion }
// PUT               → actualiza  { id, titulo, duracion, orden }
// DELETE ?id=X      → elimina tema (en cascada sus materiales)
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
require_once __DIR__ . '/html-helper.php';
$pdo  = obtenerPDO();
$user = requireAuth($pdo);

$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────────────────────
if ($metodo === 'GET') {
    // Migración automática: añadir columnas si no existen
    try { $pdo->exec('ALTER TABLE temas ADD COLUMN IF NOT EXISTS color VARCHAR(20) DEFAULT NULL'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE temas ADD COLUMN IF NOT EXISTS descripcion TEXT DEFAULT NULL'); } catch (PDOException $e) {}

    $cursoId = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0;
    if (!$cursoId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Falta curso_id']);
        exit;
    }
    // bloqueado_hasta = subquery per-alumna. Solo devuelve valor si:
    //   - quien pide es alumno
    //   - está marcado como es_alumna_rocio = 1
    //   - tiene una fila propia en temas_bloqueos_alumno para este tema
    // En cualquier otro caso (admin, alumna no de Rocío, sin bloqueo) → NULL.
    // duracion_seg = suma de duraciones reales de los vídeos del tema.
    $stmt = $pdo->prepare(
        'SELECT t.id, t.titulo, t.descripcion, t.orden, t.color,
                (SELECT b.bloqueado_hasta
                   FROM temas_bloqueos_alumno b
                   INNER JOIN usuarios u ON u.id = b.usuario_id
                  WHERE b.tema_id = t.id AND b.usuario_id = :uid AND u.es_alumna_rocio = 1
                  LIMIT 1) AS bloqueado_hasta,
                COUNT(m.id) AS num_materiales,
                COALESCE(SUM(m.duracion_seg), 0) AS duracion_seg
         FROM temas t
         LEFT JOIN materiales m ON m.tema_id = t.id
         WHERE t.curso_id = :curso_id
         GROUP BY t.id
         ORDER BY t.orden ASC, t.id ASC'
    );
    $stmt->execute([':curso_id' => $cursoId, ':uid' => (int)$user['id']]);
    echo json_encode(['ok' => true, 'temas' => $stmt->fetchAll()]);
    exit;
}

// ── POST ─────────────────────────────────────────────────────
if ($metodo === 'POST') {
    if ($user['rol'] !== 'admin') { http_response_code(403); echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado']); exit; }
    $body = json_decode(file_get_contents('php://input'), true);
    if (empty($body['curso_id']) || empty($body['titulo'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Faltan campos obligatorios']);
        exit;
    }
    // Calcular el siguiente orden
    $stmtOrden = $pdo->prepare('SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente FROM temas WHERE curso_id = :curso_id');
    $stmtOrden->execute([':curso_id' => (int)$body['curso_id']]);
    $orden = $stmtOrden->fetchColumn();

    $stmt = $pdo->prepare(
        'INSERT INTO temas (curso_id, titulo, descripcion, duracion, orden, color) VALUES (:curso_id, :titulo, :descripcion, :duracion, :orden, :color)'
    );
    $stmt->execute([
        ':curso_id'    => (int)$body['curso_id'],
        ':titulo'      => trim($body['titulo']),
        ':descripcion' => !empty($body['descripcion']) ? limpiarHtml($body['descripcion']) : null,
        ':duracion'    => trim($body['duracion'] ?? ''),
        ':orden'       => $orden,
        ':color'       => null,
    ]);
    $temaId  = $pdo->lastInsertId();
    $adminId = isset($body['admin_id']) ? (int)$body['admin_id'] : 0;
    registrar_log($pdo, 'tema_creado', 'Tema "' . trim($body['titulo']) . '" creado en curso ID ' . (int)$body['curso_id'], $adminId);
    echo json_encode(['ok' => true, 'id' => $temaId, 'orden' => $orden]);
    exit;
}

// ── PUT ──────────────────────────────────────────────────────
if ($metodo === 'PUT') {
    if ($user['rol'] !== 'admin') { http_response_code(403); echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado']); exit; }
    $body = json_decode(file_get_contents('php://input'), true);
    if (empty($body['id'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Falta el id del tema']);
        exit;
    }
    $stmt = $pdo->prepare(
        'UPDATE temas SET titulo=:titulo, descripcion=:descripcion, duracion=:duracion, orden=:orden, color=:color WHERE id=:id'
    );
    $stmt->execute([
        ':titulo'      => trim($body['titulo'] ?? ''),
        ':descripcion' => isset($body['descripcion']) ? limpiarHtml($body['descripcion']) : null,
        ':duracion'    => trim($body['duracion'] ?? ''),
        ':orden'       => (int)($body['orden'] ?? 0),
        ':color'       => !empty($body['color']) ? trim($body['color']) : null,
        ':id'          => (int)$body['id'],
    ]);
    $adminIdPut = isset($body['admin_id']) ? (int)$body['admin_id'] : 0;
    registrar_log($pdo, 'tema_editado', 'Tema "' . trim($body['titulo'] ?? '') . '" editado (ID ' . (int)$body['id'] . ')', $adminIdPut);
    echo json_encode(['ok' => true]);
    exit;
}

// ── PATCH — reordenar temas ──────────────────────────────────
// Body: { ordenes: [{id, orden}, …] }
if ($metodo === 'PATCH') {
    if ($user['rol'] !== 'admin') { http_response_code(403); echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado']); exit; }
    $body = json_decode(file_get_contents('php://input'), true);
    if (empty($body['ordenes']) || !is_array($body['ordenes'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Falta el array ordenes']);
        exit;
    }
    $stmt = $pdo->prepare('UPDATE temas SET orden = :orden WHERE id = :id');
    $pdo->beginTransaction();
    try {
        foreach ($body['ordenes'] as $item) {
            $stmt->execute([':orden' => (int)$item['orden'], ':id' => (int)$item['id']]);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['ok' => false, 'mensaje' => 'Error al guardar el orden']);
        exit;
    }
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
        echo json_encode(['ok' => false, 'mensaje' => 'Falta el id del tema']);
        exit;
    }
    // Recuperar título antes de borrar para el log
    $stmtTit = $pdo->prepare('SELECT titulo FROM temas WHERE id = :id');
    $stmtTit->execute([':id' => $id]);
    $tituloTema = (string)$stmtTit->fetchColumn();

    // Eliminar archivos físicos de los materiales del tema
    $stmtMat = $pdo->prepare('SELECT ruta FROM materiales WHERE tema_id = :tema_id');
    $stmtMat->execute([':tema_id' => $id]);
    $materiales = $stmtMat->fetchAll();
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
    foreach ($materiales as $mat) {
        $ruta = $docRoot . $mat['ruta'];
        if (file_exists($ruta)) { @unlink($ruta); }
    }
    // El FK CASCADE eliminará los materiales en BD
    $stmt = $pdo->prepare('DELETE FROM temas WHERE id = :id');
    $stmt->execute([':id' => $id]);

    registrar_log($pdo, 'tema_eliminado', 'Tema "' . $tituloTema . '" eliminado (ID ' . $id . ')', 0);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
