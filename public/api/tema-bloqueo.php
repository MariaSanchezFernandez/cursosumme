<?php
// ─────────────────────────────────────────────────────────────
// api/tema-bloqueo.php  —  Bloqueo temporal de un tema
//
// POST  { id, bloqueado_hasta }   → bloquea hasta esa fecha/hora (admin)
// POST  { id, bloqueado_hasta:null } → desbloquea inmediatamente (admin)
//
// `bloqueado_hasta` debe llegar en formato ISO local sin TZ
// (ej. "2026-06-15T18:30") o como NULL.
//
// La validez del bloqueo se comprueba siempre con NOW() del servidor:
// si `bloqueado_hasta` queda en el pasado se considera desbloqueado
// automáticamente sin necesidad de acción del admin.
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
$pdo  = obtenerPDO();
$user = requireAdmin($pdo);

// Migración defensiva por si este endpoint corre antes que temas.php GET
try { $pdo->exec('ALTER TABLE temas ADD COLUMN IF NOT EXISTS bloqueado_hasta DATETIME DEFAULT NULL'); } catch (PDOException $e) {}

$body = json_decode(file_get_contents('php://input'), true);
$id   = (int)($body['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Falta el id del tema']);
    exit;
}

// Normalizar bloqueado_hasta: NULL para desbloquear, o "YYYY-MM-DD HH:MM:SS"
$bh = $body['bloqueado_hasta'] ?? null;
$fechaSql = null;
if ($bh !== null && $bh !== '') {
    // Acepta "2026-06-15T18:30", "2026-06-15T18:30:00" o "2026-06-15 18:30"
    $bh = trim((string)$bh);
    $bh = str_replace('T', ' ', $bh);
    // Asegurar segundos
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
    // No permitir bloqueos en el pasado: equivaldría a un noop confuso
    if ($ts <= time()) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'La fecha de desbloqueo debe ser futura']);
        exit;
    }
    $fechaSql = $bh;
}

// Recuperar título para el log
$stmtTit = $pdo->prepare('SELECT titulo FROM temas WHERE id = :id');
$stmtTit->execute([':id' => $id]);
$titulo = (string)$stmtTit->fetchColumn();
if ($titulo === '') {
    http_response_code(404);
    echo json_encode(['ok' => false, 'mensaje' => 'Tema no encontrado']);
    exit;
}

$stmt = $pdo->prepare('UPDATE temas SET bloqueado_hasta = :bh WHERE id = :id');
$stmt->execute([':bh' => $fechaSql, ':id' => $id]);

$adminId = (int)($user['id'] ?? 0);
if ($fechaSql === null) {
    registrar_log($pdo, 'tema_desbloqueado', 'Tema "' . $titulo . '" desbloqueado (ID ' . $id . ')', $adminId);
} else {
    registrar_log($pdo, 'tema_bloqueado', 'Tema "' . $titulo . '" bloqueado hasta ' . $fechaSql . ' (ID ' . $id . ')', $adminId);
}

echo json_encode(['ok' => true, 'bloqueado_hasta' => $fechaSql]);
