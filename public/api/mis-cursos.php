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
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/db-connect.php';
$pdo  = obtenerPDO();
$user = requireAuth($pdo);

$usuarioId = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
if (!$usuarioId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Falta usuario_id']);
    exit;
}

// Solo puedes ver tus propios cursos, salvo que seas admin
if ($usuarioId !== (int)$user['id'] && $user['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado']);
    exit;
}


// Cursos asignados al alumno (activos)
$stmtCursos = $pdo->prepare(
    'SELECT c.id, c.titulo, c.descripcion, c.etiqueta, c.nivel, c.color, c.pack_color, c.imagen
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

// Temas de los cursos del alumno. duracion_seg sale de la suma de
// duraciones reales de los vídeos (materiales.duracion_seg) — fuente única.
$stmtTemas = $pdo->prepare(
    "SELECT t.id, t.curso_id, t.titulo, t.descripcion, t.orden,
            COALESCE(SUM(m.duracion_seg), 0) AS duracion_seg
     FROM temas t
     LEFT JOIN materiales m ON m.tema_id = t.id
     WHERE t.curso_id IN ({$placeholders})
     GROUP BY t.id
     ORDER BY t.curso_id ASC, t.orden ASC, t.id ASC"
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
    "SELECT id, tema_id, tipo, nombre, ruta, tamano_kb, vdocipher_video_id, vdo_status
     FROM materiales
     WHERE tema_id IN ({$phTemas})
     ORDER BY tema_id ASC, orden ASC, subido_en ASC"
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

// Unir temas con curso y calcular duracion_seg total del curso
// como suma de duracion_seg de cada tema. Así el cliente recibe
// las dos magnitudes ya calculadas y solo tiene que formatearlas.
foreach ($cursos as &$c) {
    $temasC = $temasPorCurso[$c['id']] ?? [];
    $c['temas']        = $temasC;
    $c['duracion_seg'] = array_sum(array_column($temasC, 'duracion_seg'));
}
unset($c);

echo json_encode(['ok' => true, 'cursos' => $cursos]);
