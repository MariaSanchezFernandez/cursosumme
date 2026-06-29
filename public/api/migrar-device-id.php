<?php
// ─────────────────────────────────────────────────────────────
// api/migrar-device-id.php
// ONE-SHOT: añade sesiones.device_id y sube max_sesiones a 3.
//
// Idempotente (usa IF NOT EXISTS), seguro re-ejecutar.
// Auth: BACKUP_SECRET por POST JSON.
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
                ADD COLUMN IF NOT EXISTS device_id VARCHAR(64) DEFAULT NULL");
    $pasos[] = 'Columna sesiones.device_id lista';

    $pdo->exec("ALTER TABLE sesiones
                ADD INDEX IF NOT EXISTS idx_device_id (usuario_id, device_id)");
    $pasos[] = 'Índice idx_device_id listo';

    $pdo->exec("ALTER TABLE usuarios
                MODIFY COLUMN max_sesiones TINYINT UNSIGNED NOT NULL DEFAULT 3");
    $pasos[] = 'Default max_sesiones actualizado a 3';

    $stmt   = $pdo->query("UPDATE usuarios SET max_sesiones = 3 WHERE max_sesiones = 2");
    $pasos[] = "Usuarios actualizados de 2→3 dispositivos: {$stmt->rowCount()}";

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
