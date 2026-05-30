<?php
// ─────────────────────────────────────────────────────────────
// api/migrar-quitar-cloudflare.php
// ONE-SHOT: elimina las columnas residuales cf_video_id y cf_status
// de la tabla `materiales`. Sobrantes desde la migración a VdoCipher
// (mayo 2026); ya no hay código que las lea ni las escriba.
//
// Idempotente: comprueba INFORMATION_SCHEMA antes de tirar cada columna,
// así una segunda llamada no falla.
//
// POST { "key": "<BACKUP_SECRET>", "dry_run": true|false }
//   - dry_run ausente o true  → solo informe; no toca nada
//   - dry_run: false          → ejecuta los ALTER TABLE DROP COLUMN
//
// Auth: BACKUP_SECRET.
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/db-config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}
$body = json_decode(file_get_contents('php://input'), true) ?? [];
if (!defined('BACKUP_SECRET') || !hash_equals(BACKUP_SECRET, (string)($body['key'] ?? ''))) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado']);
    exit;
}
// Por seguridad: ausente o true = solo informe. Solo actúa con dry_run: false explícito.
$dryRun = !(array_key_exists('dry_run', $body) && $body['dry_run'] === false);

try {
    $pdo = obtenerPDO();

    // Datos pre-eliminación: por si las columnas tuviesen valores no NULL,
    // los reportamos para que el operador lo vea (no bloquea el drop).
    $previo = [];
    foreach (['cf_video_id', 'cf_status'] as $col) {
        $existe = (bool)$pdo->query(
            "SELECT 1 FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'materiales'
               AND column_name = '" . $col . "' LIMIT 1"
        )->fetchColumn();
        if ($existe) {
            $noNull = (int)$pdo->query("SELECT COUNT(*) FROM materiales WHERE `{$col}` IS NOT NULL")->fetchColumn();
            $previo[$col] = ['existe' => true, 'filas_no_null' => $noNull];
        } else {
            $previo[$col] = ['existe' => false];
        }
    }

    $acciones = [];
    if (!$dryRun) {
        foreach (['cf_video_id', 'cf_status'] as $col) {
            if ($previo[$col]['existe']) {
                $pdo->exec("ALTER TABLE materiales DROP COLUMN `{$col}`");
                $acciones[] = "DROP COLUMN {$col}";
            }
        }
    }

    echo json_encode([
        'ok'       => true,
        'dry_run'  => $dryRun,
        'previo'   => $previo,
        'acciones' => $acciones,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('migrar-quitar-cloudflare: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
}
