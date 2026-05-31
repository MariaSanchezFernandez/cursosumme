<?php
// ─────────────────────────────────────────────────────────────
// api/bloqueo-tema-alumno.php  —  Bloqueo de un tema para una
// alumna concreta (solo aplica a usuarias con es_alumna_rocio=1).
//
// GET   ?usuario_id=X
//       → lista todos los bloqueos activos de esa alumna:
//         [{ tema_id, bloqueado_hasta }, ...]
//
// POST  { usuario_id, tema_id, bloqueado_hasta }   (admin)
//       → si bloqueado_hasta es null/'' → desbloquea (borra fila)
//       → si es fecha futura ISO → crea/actualiza el bloqueo
//
// `bloqueado_hasta` formato: "YYYY-MM-DDTHH:MM" o "YYYY-MM-DD HH:MM[:SS]".
//
// El bloqueo solo se permite sobre usuarias con es_alumna_rocio = 1
// (los demás alumnos jamás tienen temas bloqueados).
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
$pdo  = obtenerPDO();
$user = requireAdmin($pdo);

$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET: lista de bloqueos de una alumna ─────────────────────
if ($metodo === 'GET') {
    $usuarioId = (int)($_GET['usuario_id'] ?? 0);
    if (!$usuarioId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Falta usuario_id']);
        exit;
    }
    $stmt = $pdo->prepare(
        'SELECT tema_id, DATE_FORMAT(bloqueado_hasta, "%Y-%m-%dT%H:%i") AS bloqueado_hasta
         FROM temas_bloqueos_alumno
         WHERE usuario_id = :uid'
    );
    $stmt->execute([':uid' => $usuarioId]);
    echo json_encode(['ok' => true, 'bloqueos' => $stmt->fetchAll()]);
    exit;
}

// ── POST: alta/baja de un bloqueo ────────────────────────────
if ($metodo !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$usuarioId = (int)($body['usuario_id'] ?? 0);
$temaId    = (int)($body['tema_id']    ?? 0);
if (!$usuarioId || !$temaId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Faltan usuario_id y/o tema_id']);
    exit;
}

// El alumno debe existir, ser alumno y estar marcado como "de Rocío"
$chk = $pdo->prepare(
    "SELECT nombre, es_alumna_rocio FROM usuarios
     WHERE id = :id AND rol = 'alumno' LIMIT 1"
);
$chk->execute([':id' => $usuarioId]);
$alumno = $chk->fetch();
if (!$alumno) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'mensaje' => 'Alumno no encontrado']);
    exit;
}
if (empty($alumno['es_alumna_rocio'])) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'mensaje' => 'Este alumno no está marcado como Alumna de Rocío']);
    exit;
}

// El tema debe existir y devolvemos su título para el log
$stmtTit = $pdo->prepare('SELECT titulo FROM temas WHERE id = :id');
$stmtTit->execute([':id' => $temaId]);
$titulo = (string)$stmtTit->fetchColumn();
if ($titulo === '') {
    http_response_code(404);
    echo json_encode(['ok' => false, 'mensaje' => 'Tema no encontrado']);
    exit;
}

$bh = $body['bloqueado_hasta'] ?? null;
$adminId = (int)($user['id'] ?? 0);

// Desbloquear: borrar la fila
if ($bh === null || $bh === '') {
    $del = $pdo->prepare(
        'DELETE FROM temas_bloqueos_alumno WHERE usuario_id = :uid AND tema_id = :tid'
    );
    $del->execute([':uid' => $usuarioId, ':tid' => $temaId]);
    registrar_log(
        $pdo,
        'tema_desbloqueado_alumna',
        "Tema \"{$titulo}\" desbloqueado para {$alumno['nombre']} (alumno ID {$usuarioId})",
        $adminId
    );
    echo json_encode(['ok' => true, 'bloqueado_hasta' => null]);
    exit;
}

// Bloquear/actualizar: normalizar fecha
$bh = trim((string)$bh);
$bh = str_replace('T', ' ', $bh);
if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $bh)) {
    $bh .= ':00';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $bh)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Formato de fecha inválido']);
    exit;
}
$ts = strtotime($bh);
if ($ts === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Fecha no parseable']);
    exit;
}
if ($ts <= time()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'La fecha de desbloqueo debe ser futura']);
    exit;
}

// UPSERT en temas_bloqueos_alumno
$ins = $pdo->prepare(
    'INSERT INTO temas_bloqueos_alumno (usuario_id, tema_id, bloqueado_hasta)
     VALUES (:uid, :tid, :bh)
     ON DUPLICATE KEY UPDATE bloqueado_hasta = VALUES(bloqueado_hasta)'
);
$ins->execute([':uid' => $usuarioId, ':tid' => $temaId, ':bh' => $bh]);

registrar_log(
    $pdo,
    'tema_bloqueado_alumna',
    "Tema \"{$titulo}\" bloqueado hasta {$bh} para {$alumno['nombre']} (alumno ID {$usuarioId})",
    $adminId
);

echo json_encode(['ok' => true, 'bloqueado_hasta' => $bh]);
