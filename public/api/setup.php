<?php
// ─────────────────────────────────────────────────────────────
// api/setup.php  —  Migraciones incrementales de la BD
// Visitar una vez tras cada despliegue que añada columnas.
// Seguro: usa IF NOT EXISTS, no destruye datos.
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();

$migraciones = [];

// v2: columna pack en cursos
try {
    $pdo->exec('ALTER TABLE cursos ADD COLUMN IF NOT EXISTS pack VARCHAR(120) DEFAULT NULL');
    $migraciones[] = 'pack en cursos: OK';
} catch (Exception $e) {
    $migraciones[] = 'pack en cursos: ' . $e->getMessage();
}

echo json_encode(['ok' => true, 'migraciones' => $migraciones]);
