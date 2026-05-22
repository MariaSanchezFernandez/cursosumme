<?php
// ─────────────────────────────────────────────────────────────
// api/logout.php  —  Invalida el token de la sesión actual en BD
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();

$header = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $header = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $header  = $headers['Authorization'] ?? '';
}

$token = '';
if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $m)) {
    $token = trim($m[1]);
}

if ($token) {
    $pdo->prepare('DELETE FROM sesiones WHERE token = :t')->execute([':t' => $token]);
}

echo json_encode(['ok' => true]);
