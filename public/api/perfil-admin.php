<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
$pdo = obtenerPDO();

// ── GET: datos del admin ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
    if (!$id) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'Falta usuario_id']); exit; }

    $stmt = $pdo->prepare('SELECT id, nombre, apellidos, email FROM usuarios WHERE id = ? AND rol = "admin"');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); echo json_encode(['ok' => false, 'error' => 'Admin no encontrado']); exit; }

    echo json_encode(['ok' => true, 'admin' => $row]);
    exit;
}

// ── PUT: actualizar nombre, apellidos, email ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true);
    $id   = isset($body['usuario_id']) ? (int)$body['usuario_id'] : 0;
    if (!$id) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'Falta usuario_id']); exit; }

    $nombre    = isset($body['nombre'])    ? trim($body['nombre'])    : null;
    $apellidos = isset($body['apellidos']) ? trim($body['apellidos']) : null;
    $email     = isset($body['email'])     ? trim(strtolower($body['email'])) : null;

    if (!$nombre || !$apellidos || !$email) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Faltan campos: nombre, apellidos, email']);
        exit;
    }

    // Verificar email no usado por otro usuario
    $chk = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id != ?');
    $chk->execute([$email, $id]);
    if ($chk->fetch()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'Ese email ya está en uso']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE usuarios SET nombre = ?, apellidos = ?, email = ? WHERE id = ? AND rol = "admin"');
    $stmt->execute([$nombre, $apellidos, $email, $id]);

    registrar_log($pdo, 'perfil_actualizado', "Perfil admin actualizado ({$email})", $id);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
