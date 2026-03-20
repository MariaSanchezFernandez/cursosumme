<?php
// ─────────────────────────────────────────────────────────────
// api/mis-cursos.php  —  Cursos asignados a un alumno
// GET  ?usuario_id=X  → cursos del alumno con temas y materiales
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$usuarioId = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
if (!$usuarioId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Falta usuario_id']);
    exit;
}

require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();


// Cursos asignados al alumno (activos)
$stmtCursos = $pdo->prepare(
    'SELECT c.id, c.titulo, c.descripcion, c.etiqueta, c.nivel, c.duracion, c.color, c.pack_color
     FROM cursos c
     INNER JOIN usuarios_cursos uc ON uc.curso_id = c.id
     WHERE uc.usuario_id = :uid AND c.activo = 1
     ORDER BY uc.asignado_en ASC'
);
$stmtCursos->execute([':uid' => $usuarioId]);
$cursos = $stmtCursos->fetchAll();

if (empty($cursos)) {
    echo json_encode(['ok' => true, 'cursos' => []]);
    exit;
}

$cursoIds = array_column($cursos, 'id');
$placeholders = implode(',', array_fill(0, count($cursoIds), '?'));

// Temas de todos esos cursos
$stmtTemas = $pdo->prepare(
    "SELECT id, curso_id, titulo, descripcion, duracion, orden
     FROM temas
     WHERE curso_id IN ({$placeholders})
     ORDER BY curso_id ASC, orden ASC, id ASC"
);
$stmtTemas->execute($cursoIds);
$temasRows = $stmtTemas->fetchAll();

if (empty($temasRows)) {
    // Devolver cursos sin temas
    foreach ($cursos as &$c) { $c['temas'] = []; }
    echo json_encode(['ok' => true, 'cursos' => $cursos]);
    exit;
}

$temaIds = array_column($temasRows, 'id');
$phTemas = implode(',', array_fill(0, count($temaIds), '?'));

// Materiales de todos esos temas
$stmtMat = $pdo->prepare(
    "SELECT id, tema_id, tipo, nombre, ruta, tamano_kb
     FROM materiales
     WHERE tema_id IN ({$phTemas})
     ORDER BY tema_id ASC, subido_en ASC"
);
$stmtMat->execute($temaIds);
$materialesRows = $stmtMat->fetchAll();

// Agrupar materiales por tema
$matPorTema = [];
foreach ($materialesRows as $m) {
    $matPorTema[$m['tema_id']][] = $m;
}

// Agrupar temas por curso
$temasPorCurso = [];
foreach ($temasRows as $t) {
    $t['materiales'] = $matPorTema[$t['id']] ?? [];
    $temasPorCurso[$t['curso_id']][] = $t;
}

// Unir todo
foreach ($cursos as &$c) {
    $c['temas'] = $temasPorCurso[$c['id']] ?? [];
}
unset($c);

echo json_encode(['ok' => true, 'cursos' => $cursos]);
