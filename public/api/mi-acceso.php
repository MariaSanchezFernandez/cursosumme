<?php
// ─────────────────────────────────────────────────────────────
// api/mi-acceso.php  —  Devuelve fecha_baja del usuario autenticado
//
//   GET  (token via X-Token header)  →  { ok, fecha_baja }
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-config.php';
require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();
$u   = requireAuth($pdo);

$st = $pdo->prepare('SELECT fecha_baja FROM usuarios WHERE id = :id LIMIT 1');
$st->execute([':id' => $u['id']]);
$fila = $st->fetch();

echo json_encode([
    'ok'         => true,
    'fecha_baja' => $fila ? $fila['fecha_baja'] : null,
]);
