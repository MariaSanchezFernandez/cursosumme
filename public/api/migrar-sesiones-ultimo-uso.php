<?php
// ─────────────────────────────────────────────────────────────
// api/migrar-sesiones-ultimo-uso.php
// ONE-SHOT: añade la columna `sesiones.ultimo_uso` + su índice.
//
// Idempotente (usa IF NOT EXISTS), seguro re-ejecutar.
// Auth: BACKUP_SECRET por POST JSON (mismo patrón del resto de
// scripts de migración del proyecto).
//
// IMPORTANTE: hacer `npm run backup-db` antes de ejecutar.
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

$pdo   = obtenerPDO();
$pasos = [];

try {
    $pdo->exec("ALTER TABLE sesiones
                ADD COLUMN IF NOT EXISTS ultimo_uso DATETIME
                  NOT NULL DEFAULT CURRENT_TIMESTAMP");
    $pasos[] = 'Columna sesiones.ultimo_uso lista';

    $pdo->exec("ALTER TABLE sesiones
                ADD INDEX IF NOT EXISTS idx_ultimo_uso (ultimo_uso)");
    $pasos[] = 'Índice idx_ultimo_uso listo';

    // Verificación: leer una fila para confirmar la columna
    $stmt = $pdo->query("SHOW COLUMNS FROM sesiones LIKE 'ultimo_uso'");
    $col  = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        throw new RuntimeException('La columna ultimo_uso no aparece tras el ALTER.');
    }
    $pasos[] = "Verificado: tipo {$col['Type']}, default {$col['Default']}";

    echo json_encode(['ok' => true, 'pasos' => $pasos], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'mensaje' => 'Error aplicando la migración',
        'detalle' => $e->getMessage(),
        'pasos'   => $pasos,
    ], JSON_UNESCAPED_UNICODE);
}
