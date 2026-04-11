<?php
// ─────────────────────────────────────────────────────────────
// api/errores.php  —  Lista errores de frontend (solo admin)
// GET ?limit=100&offset=0&tipo=
// DELETE ?id=N  → borra un error
// DELETE (sin param) → borra todos
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit  = min((int)($_GET['limit']  ?? 100), 500);
    $offset = (int)($_GET['offset'] ?? 0);
    $tipo   = $_GET['tipo'] ?? '';

    $where = $tipo ? 'WHERE tipo = :tipo' : '';
    $params = $tipo ? [':tipo' => $tipo] : [];

    $total = $pdo->prepare("SELECT COUNT(*) FROM errores $where");
    $total->execute($params);
    $count = (int)$total->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT id, tipo, mensaje, url_pagina, linea, columna, stack,
                usuario_id, usuario_email, user_agent, creado_en
         FROM errores $where
         ORDER BY creado_en DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(['ok' => true, 'total' => $count, 'errores' => $stmt->fetchAll()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id) {
        $pdo->prepare('DELETE FROM errores WHERE id = ?')->execute([$id]);
    } else {
        $pdo->exec('DELETE FROM errores');
    }
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false]);
