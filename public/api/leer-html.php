<?php
// Lectura temporal de descripciones para mejora — protegido con SETUP_KEY
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db-connect.php';
if (($_GET['key'] ?? '') !== SETUP_KEY) { http_response_code(403); exit; }
$pdo = obtenerPDO();
$cursos = $pdo->query('SELECT id, titulo, descripcion FROM cursos ORDER BY id')->fetchAll();
$temas  = $pdo->query('SELECT id, titulo, descripcion FROM temas  ORDER BY id')->fetchAll();
echo json_encode(['cursos' => $cursos, 'temas' => $temas], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
