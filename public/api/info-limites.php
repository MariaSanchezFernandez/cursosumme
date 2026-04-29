<?php
header('Content-Type: application/json; charset=utf-8');

// Espacio en disco del filesystem que aloja /uploads
$rutaUploads = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/uploads';
$libre = @disk_free_space($rutaUploads);
$total = @disk_total_space($rutaUploads);
$mb = static fn ($b) => $b === false || $b === null ? null : round($b / 1024 / 1024, 1);
$gb = static fn ($b) => $b === false || $b === null ? null : round($b / 1024 / 1024 / 1024, 2);

echo json_encode([
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size'       => ini_get('post_max_size'),
    'memory_limit'        => ini_get('memory_limit'),
    'max_execution_time'  => ini_get('max_execution_time'),
    'max_input_time'      => ini_get('max_input_time'),
    'php_version'         => PHP_VERSION,
    'disco_libre_mb'      => $mb($libre),
    'disco_libre_gb'      => $gb($libre),
    'disco_total_gb'      => $gb($total),
    'disco_uso_pct'       => ($libre && $total) ? round((1 - $libre / $total) * 100, 1) : null,
], JSON_PRETTY_PRINT);
