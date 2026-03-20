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
$pdo = obtenerPDO();

$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────────────────────
if ($metodo === 'GET') {
    // Migraciones automáticas
    try { $pdo->exec('ALTER TABLE cursos ADD COLUMN IF NOT EXISTS pack VARCHAR(120) DEFAULT NULL'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE cursos ADD COLUMN IF NOT EXISTS pack_color VARCHAR(20) DEFAULT NULL'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE cursos ADD COLUMN IF NOT EXISTS color VARCHAR(20) DEFAULT NULL'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE cursos ADD COLUMN IF NOT EXISTS descripcion TEXT DEFAULT NULL'); } catch (PDOException $e) {}

    $stmt = $pdo->query(
        'SELECT c.id, c.titulo, c.descripcion, c.etiqueta, c.nivel, c.duracion, c.pack, c.pack_color, c.color, c.activo, c.creado_en,
                COUNT(t.id) AS num_temas
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
    $campos = ['titulo', 'etiqueta', 'nivel'];
    foreach ($campos as $c) {
        if (empty($body[$c])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'mensaje' => "Falta el campo: {$c}"]);
            exit;
        }
    }
    $stmt = $pdo->prepare(
        'INSERT INTO cursos (titulo, descripcion, etiqueta, nivel, duracion, pack) VALUES (:titulo, :descripcion, :etiqueta, :nivel, :duracion, :pack)'
    );
    $stmt->execute([
        ':titulo'      => trim($body['titulo']),
        ':descripcion' => !empty($body['descripcion']) ? trim($body['descripcion']) : null,
        ':etiqueta'    => trim($body['etiqueta']),
        ':nivel'       => trim($body['nivel']),
        ':duracion'    => trim($body['duracion'] ?? ''),
        ':pack'        => !empty($body['pack']) ? trim($body['pack']) : null,
    ]);
    echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
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
         duracion=:duracion, pack=:pack, pack_color=:pack_color, color=:color, activo=:activo WHERE id=:id'
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
        ':activo'      => isset($body['activo']) ? (int)$body['activo'] : 1,
        ':id'          => (int)$body['id'],
    ]);
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
    $pdo->prepare('DELETE FROM cursos WHERE id = :id')->execute([':id' => $id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
