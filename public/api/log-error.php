<?php
// ─────────────────────────────────────────────────────────────
// api/log-error.php  —  Recibe errores JS del frontend y los guarda
// POST JSON: { tipo, mensaje, url, linea, columna, stack, usuario_id, usuario_email }
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || empty($body['mensaje'])) {
    echo json_encode(['ok' => false]);
    exit;
}

// Ignorar errores triviales de extensiones de navegador
$msg = $body['mensaje'] ?? '';
if (
    $msg === 'Script error.' ||
    str_contains($msg, 'extension://') ||
    str_contains($body['url'] ?? '', 'extension://')
) {
    echo json_encode(['ok' => true, 'ignorado' => true]);
    exit;
}

require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();

$stmt = $pdo->prepare(
    'INSERT INTO errores (tipo, mensaje, url_pagina, linea, columna, stack, usuario_id, usuario_email, user_agent)
     VALUES (:tipo, :mensaje, :url, :linea, :columna, :stack, :uid, :email, :ua)'
);
$stmt->execute([
    ':tipo'    => substr($body['tipo']    ?? 'js',        0, 30),
    ':mensaje' => substr($body['mensaje'] ?? '',          0, 2000),
    ':url'     => substr($body['url']     ?? '',          0, 500),
    ':linea'   => isset($body['linea'])   ? (int)$body['linea']   : null,
    ':columna' => isset($body['columna']) ? (int)$body['columna'] : null,
    ':stack'   => substr($body['stack']   ?? '',          0, 5000),
    ':uid'     => isset($body['usuario_id'])    ? (int)$body['usuario_id']        : null,
    ':email'   => substr($body['usuario_email'] ?? '',    0, 255),
    ':ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
]);

echo json_encode(['ok' => true]);
