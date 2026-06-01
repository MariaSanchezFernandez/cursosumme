<?php
// api/debug-bloqueos.php — RETIRADO. Era un endpoint temporal para
// diagnosticar la migración de bloqueos al sistema per-alumna.
header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode(['ok' => false, 'mensaje' => 'Endpoint retirado']);
