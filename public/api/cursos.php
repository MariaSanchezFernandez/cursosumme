<?php
// ─────────────────────────────────────────────────────────────
// api/cursos.php  —  CRUD de cursos
// GET    → lista todos los cursos con número de temas
// POST   → crea un nuevo curso  { titulo, etiqueta, nivel, duracion }
// PUT    → actualiza un curso   { id, titulo, etiqueta, nivel, duracion, activo }
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
$pdo = obtenerPDO();

$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────────────────────
if ($metodo === 'GET') {
    // duracion_seg = suma de duracion_seg de cada material en cada
    // tema del curso. Se usa una subquery porque hacer LEFT JOIN a
    // materiales junto al de temas multiplicaría filas y rompería
    // el COUNT(t.id) AS num_temas.
    $stmt = $pdo->query(
        'SELECT c.id, c.titulo, c.descripcion, c.etiqueta, c.nivel, c.duracion, c.pack, c.pack_color, c.color, c.imagen, c.activo, c.creado_en,
                COUNT(t.id) AS num_temas,
                (SELECT COALESCE(SUM(m.duracion_seg), 0)
                 FROM materiales m
                 INNER JOIN temas tt ON tt.id = m.tema_id
                 WHERE tt.curso_id = c.id) AS duracion_seg
         FROM cursos c
         LEFT JOIN temas t ON t.curso_id = c.id
         GROUP BY c.id
         ORDER BY c.pack IS NULL, c.pack, c.creado_en DESC'
    );
    echo json_encode(['ok' => true, 'cursos' => $stmt->fetchAll()]);
    exit;
}

// ── POST ─────────────────────────────────────────────────────
if ($metodo === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (empty($body['titulo']) || empty($body['etiqueta'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Faltan campos obligatorios']);
        exit;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO cursos (titulo, descripcion, etiqueta, nivel, duracion, pack, imagen) VALUES (:titulo, :descripcion, :etiqueta, :nivel, :duracion, :pack, :imagen)'
    );
    $stmt->execute([
        ':titulo'      => trim($body['titulo']),
        ':descripcion' => !empty($body['descripcion']) ? trim($body['descripcion']) : null,
        ':etiqueta'    => trim($body['etiqueta']),
        ':nivel'       => trim($body['nivel']),
        ':duracion'    => trim($body['duracion'] ?? ''),
        ':pack'        => !empty($body['pack']) ? trim($body['pack']) : null,
        ':imagen'      => !empty($body['imagen']) ? trim($body['imagen']) : null,
    ]);
    $newId = $pdo->lastInsertId();
    $adminIdPost = isset($body['admin_id']) ? (int)$body['admin_id'] : 0;
    registrar_log($pdo, 'curso_creado', "Curso \"" . trim($body['titulo']) . "\" creado", $adminIdPost);
    echo json_encode(['ok' => true, 'id' => $newId]);
    exit;
}

// ── PUT ──────────────────────────────────────────────────────
if ($metodo === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (empty($body['id'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Falta el id del curso']);
        exit;
    }
    $stmt = $pdo->prepare(
        'UPDATE cursos SET titulo=:titulo, descripcion=:descripcion, etiqueta=:etiqueta, nivel=:nivel,
         duracion=:duracion, pack=:pack, pack_color=:pack_color, color=:color, imagen=:imagen, activo=:activo WHERE id=:id'
    );
    $stmt->execute([
        ':titulo'      => trim($body['titulo'] ?? ''),
        ':descripcion' => isset($body['descripcion']) ? trim($body['descripcion']) : null,
        ':etiqueta'    => trim($body['etiqueta'] ?? ''),
        ':nivel'       => trim($body['nivel'] ?? ''),
        ':duracion'    => trim($body['duracion'] ?? ''),
        ':pack'        => !empty($body['pack']) ? trim($body['pack']) : null,
        ':pack_color'  => !empty($body['pack_color']) ? trim($body['pack_color']) : null,
        ':color'       => !empty($body['color']) ? trim($body['color']) : null,
        ':imagen'      => !empty($body['imagen']) ? trim($body['imagen']) : null,
        ':activo'      => isset($body['activo']) ? (int)$body['activo'] : 1,
        ':id'          => (int)$body['id'],
    ]);
    $adminIdPut = isset($body['admin_id']) ? (int)$body['admin_id'] : 0;
    registrar_log($pdo, 'curso_editado', "Curso \"" . trim($body['titulo'] ?? '') . "\" editado", $adminIdPut);
    echo json_encode(['ok' => true]);
    exit;
}

// ── DELETE ───────────────────────────────────────────────────
if ($metodo === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Falta el id del curso']);
        exit;
    }
    // Borrar materiales físicos y registros de temas/materiales en cascada
    // (la BD debe tener ON DELETE CASCADE o lo borramos manualmente)
    $temas = $pdo->prepare('SELECT id FROM temas WHERE curso_id = :id');
    $temas->execute([':id' => $id]);
    foreach ($temas->fetchAll() as $t) {
        $pdo->prepare('DELETE FROM materiales WHERE tema_id = :tid')->execute([':tid' => $t['id']]);
    }
    $pdo->prepare('DELETE FROM temas WHERE curso_id = :id')->execute([':id' => $id]);
    $pdo->prepare('DELETE FROM usuarios_cursos WHERE curso_id = :id')->execute([':id' => $id]);
    $rowTitulo = $pdo->prepare('SELECT titulo FROM cursos WHERE id = ?');
    $rowTitulo->execute([$id]);
    $tituloCurso = $rowTitulo->fetchColumn() ?: "ID {$id}";
    $pdo->prepare('DELETE FROM cursos WHERE id = :id')->execute([':id' => $id]);
    registrar_log($pdo, 'curso_eliminado', "Curso \"{$tituloCurso}\" eliminado", 0);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
