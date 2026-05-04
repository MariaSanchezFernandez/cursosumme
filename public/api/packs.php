<?php
// ─────────────────────────────────────────────────────────────
// api/packs.php  —  CRUD de packs
// GET           → lista packs activos (público)
// POST          → crear pack (admin)
// PUT  ?id=X    → editar pack (admin)
// DELETE ?id=X  → eliminar pack (admin)
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/db-config.php';

$pdo    = obtenerPDO();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $stmt = $pdo->query('SELECT * FROM packs WHERE activo=1 ORDER BY id ASC');
    echo json_encode(['ok' => true, 'packs' => $stmt->fetchAll()]);
    exit;
}

// El resto requiere admin
if (!empty($_GET['token']) && empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $_GET['token'];
}
requireAdmin($pdo);

$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($metodo === 'POST') {
    $stmt = $pdo->prepare(
        'INSERT INTO packs (nombre, descripcion, precio, stripe_price_id, etiqueta, activo)
         VALUES (:nombre, :desc, :precio, :spid, :etiqueta, 1)'
    );
    $stmt->execute([
        ':nombre'   => trim($body['nombre'] ?? ''),
        ':desc'     => trim($body['descripcion'] ?? ''),
        ':precio'   => $body['precio'] ?: null,
        ':spid'     => trim($body['stripe_price_id'] ?? '') ?: null,
        ':etiqueta' => trim($body['etiqueta'] ?? ''),
    ]);
    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    exit;
}

if ($metodo === 'PUT') {
    $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
    $pdo->prepare(
        'UPDATE packs SET nombre=:nombre, descripcion=:desc, precio=:precio,
         stripe_price_id=:spid, etiqueta=:etiqueta, activo=:activo WHERE id=:id'
    )->execute([
        ':nombre'   => trim($body['nombre'] ?? ''),
        ':desc'     => trim($body['descripcion'] ?? ''),
        ':precio'   => $body['precio'] ?: null,
        ':spid'     => trim($body['stripe_price_id'] ?? '') ?: null,
        ':etiqueta' => trim($body['etiqueta'] ?? ''),
        ':activo'   => (int)($body['activo'] ?? 1),
        ':id'       => $id,
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    $pdo->prepare('DELETE FROM packs WHERE id=:id')->execute([':id' => $id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
