<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();
requireAdmin($pdo);

$limite = isset($_GET['limite']) ? min((int)$_GET['limite'], 200) : 50;

$stmt = $pdo->query("
    SELECT l.id, l.tipo, l.descripcion, l.creado_en,
           COALESCE(CONCAT(u.nombre, ' ', u.apellidos), 'Sistema') AS actor
    FROM logs l
    LEFT JOIN usuarios u ON u.id = l.usuario_id
    ORDER BY l.creado_en DESC
    LIMIT {$limite}
");

echo json_encode(['ok' => true, 'logs' => $stmt->fetchAll()]);
