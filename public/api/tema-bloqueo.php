<?php
// api/tema-bloqueo.php — ENDPOINT RETIRADO el 2026-06-01.
// El bloqueo global por tema se sustituyó por el bloqueo per-alumna.
// Sustitúyase por /api/bloqueo-tema-alumno.php (POST con usuario_id,
// tema_id y bloqueado_hasta).
header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'ok'      => false,
    'mensaje' => 'Endpoint retirado. Usa /api/bloqueo-tema-alumno.php',
]);
