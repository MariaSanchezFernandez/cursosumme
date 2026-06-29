<?php
// ─────────────────────────────────────────────────────────────
// api/sesiones.php  —  Gestión de sesiones activas por usuario (admin)
//
//   GET  ?usuario_id=X         → { ok, activas, max, lista:[{ip,dispositivo,creado_en,expira_en}] }
//   PATCH body {usuario_id, max_sesiones} → actualiza límite
//   DELETE ?usuario_id=X       → cierra todas las sesiones del alumno
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-config.php';
require_once __DIR__ . '/db-connect.php';
$pdo   = obtenerPDO();
requireAdmin($pdo);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $uid = (int)($_GET['usuario_id'] ?? 0);
    if (!$uid) { echo json_encode(['ok' => false, 'mensaje' => 'Falta usuario_id']); exit; }

    $st = $pdo->prepare('SELECT max_sesiones FROM usuarios WHERE id = :id LIMIT 1');
    $st->execute([':id' => $uid]);
    $fila = $st->fetch();
    if (!$fila) { echo json_encode(['ok' => false, 'mensaje' => 'Usuario no encontrado']); exit; }

    // Una fila por slot de dispositivo — misma clave que usa login.php para
    // contar (COALESCE(device_id, 'sin-device')), no solo dispositivo+IP.
    // Si no se alinean, el admin puede ver menos sesiones de las que
    // login.php está contando realmente (ver memoria project-device-limit).
    // Por cada slot se muestra la fila más reciente (ip/dispositivo pueden
    // variar entre logins del mismo device_id, p.ej. móvil cambiando de red).
    $st2 = $pdo->prepare(
        'SELECT s.ip, s.dispositivo, s.creado_en, s.expira_en
         FROM sesiones s
         WHERE s.usuario_id = :id AND s.expira_en > NOW()
           AND s.creado_en = (
             SELECT MAX(s2.creado_en) FROM sesiones s2
             WHERE s2.usuario_id = s.usuario_id AND s2.expira_en > NOW()
               AND COALESCE(s2.device_id, "sin-device") = COALESCE(s.device_id, "sin-device")
           )
         ORDER BY s.creado_en DESC'
    );
    $st2->execute([':id' => $uid]);
    $lista = $st2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok'     => true,
        'activas' => count($lista),
        'max'    => (int)$fila['max_sesiones'],
        'lista'  => $lista,
    ]);
    exit;
}

if ($method === 'PATCH') {
    $body = json_decode(file_get_contents('php://input'), true);
    $uid  = (int)($body['usuario_id']  ?? 0);
    $max  = (int)($body['max_sesiones'] ?? 0);

    if (!$uid || $max < 1 || $max > 10) {
        echo json_encode(['ok' => false, 'mensaje' => 'Parámetros inválidos (max_sesiones: 1–10)']);
        exit;
    }

    $pdo->prepare('UPDATE usuarios SET max_sesiones = :m WHERE id = :id')
        ->execute([':m' => $max, ':id' => $uid]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($method === 'DELETE') {
    $uid = (int)($_GET['usuario_id'] ?? 0);
    if (!$uid) { echo json_encode(['ok' => false, 'mensaje' => 'Falta usuario_id']); exit; }

    $pdo->prepare('DELETE FROM sesiones WHERE usuario_id = :id')->execute([':id' => $uid]);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
