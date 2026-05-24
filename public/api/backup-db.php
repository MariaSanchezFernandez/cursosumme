<?php
// ─────────────────────────────────────────────────────────────
// backup-db.php  —  Exporta la BD completa en formato SQL
// Protegido con clave en POST body (NO query string para que la clave
// no quede registrada en los logs de Apache/nginx).
//   POST /api/backup-db.php
//   body: { "key": "<BACKUP_SECRET>" }
// Solo para uso interno; NO es un endpoint de alumno/admin.
// ─────────────────────────────────────────────────────────────

require_once __DIR__ . '/db-config.php';
require_once __DIR__ . '/db-connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

// ── Autenticación ──────────────────────────────────────────────
if (!defined('BACKUP_SECRET') || BACKUP_SECRET === '') {
    http_response_code(503);
    exit('BACKUP_SECRET no configurado en db-config.php');
}

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$clave = $body['key'] ?? '';
if (!hash_equals(BACKUP_SECRET, $clave)) {
    http_response_code(403);
    exit('Clave incorrecta');
}

// ── Cabeceras de descarga ──────────────────────────────────────
$fecha    = date('Y-m-d_H-i-s');
$nombre   = "backup_{$fecha}.sql";

header('Content-Type: text/plain; charset=utf-8');
header("Content-Disposition: attachment; filename=\"{$nombre}\"");
// Deshabilitar buffering para transmitir directamente
if (ob_get_level()) ob_end_clean();

// ── Conexión ───────────────────────────────────────────────────
$pdo = obtenerPDO();
$pdo->exec("SET NAMES utf8mb4");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

// ── Cabecera del archivo SQL ───────────────────────────────────
echo "-- ================================================================\n";
echo "-- Backup: " . DB_NOMBRE . "\n";
echo "-- Fecha:  " . date('d/m/Y H:i:s') . "\n";
echo "-- ================================================================\n\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n";
echo "SET NAMES utf8mb4;\n\n";

// ── Exportar tablas ────────────────────────────────────────────
$stmt   = $pdo->query("SHOW TABLES");
$tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tablas as $tabla) {
    // Estructura
    $create = $pdo->query("SHOW CREATE TABLE `{$tabla}`")
                  ->fetch(PDO::FETCH_ASSOC);
    $createSql = $create['Create Table'];

    echo "-- ----- {$tabla} -----\n";
    echo "DROP TABLE IF EXISTS `{$tabla}`;\n";
    echo $createSql . ";\n\n";

    // Datos en bloques de 500 filas
    $select = $pdo->query("SELECT * FROM `{$tabla}`");
    $columnas = null;
    $bloque   = [];
    $total    = 0;

    while ($fila = $select->fetch(PDO::FETCH_ASSOC)) {
        if ($columnas === null) {
            $columnas = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($fila)));
        }
        $vals = array_map(function ($v) use ($pdo) {
            if ($v === null) return 'NULL';
            if (is_numeric($v) && !is_string($v)) return $v;
            return $pdo->quote($v);
        }, array_values($fila));
        $bloque[] = '(' . implode(', ', $vals) . ')';
        $total++;

        if (count($bloque) >= 500) {
            echo "INSERT INTO `{$tabla}` ({$columnas}) VALUES\n";
            echo implode(",\n", $bloque) . ";\n";
            $bloque = [];
        }
    }
    if (!empty($bloque)) {
        echo "INSERT INTO `{$tabla}` ({$columnas}) VALUES\n";
        echo implode(",\n", $bloque) . ";\n";
    }
    echo "\n";
}

echo "SET FOREIGN_KEY_CHECKS = 1;\n";
