<?php
// ─────────────────────────────────────────────────────────────
// api/migrar-bloqueos-por-alumna.php
// ONE-SHOT: aplica la migración de bloqueos de temas globales
// (columna `temas.bloqueado_hasta`) a per-alumna (tabla nueva
// `temas_bloqueos_alumno`) y añade el flag `usuarios.es_alumna_rocio`.
//
// Idempotente: usa IF NOT EXISTS y vuelve a vaciar bloqueados sin error.
//
// Auth: BACKUP_SECRET por POST JSON, mismo patrón que el resto de
// migraciones del proyecto.
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

$pdo  = obtenerPDO();
$pasos = [];

try {
    // 1. Flag en usuarios
    $pdo->exec("ALTER TABLE usuarios
                ADD COLUMN IF NOT EXISTS es_alumna_rocio TINYINT(1) NOT NULL DEFAULT 0");
    $pasos[] = 'usuarios.es_alumna_rocio añadido (o ya existía)';

    // 2. Tabla de bloqueos
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS temas_bloqueos_alumno (
            usuario_id        INT      NOT NULL,
            tema_id           INT      NOT NULL,
            bloqueado_hasta   DATETIME NOT NULL,
            creado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (usuario_id, tema_id),
            KEY idx_bloqueado_hasta (bloqueado_hasta),
            CONSTRAINT fk_tba_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            CONSTRAINT fk_tba_tema    FOREIGN KEY (tema_id)    REFERENCES temas(id)    ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $pasos[] = 'tabla temas_bloqueos_alumno creada (o ya existía)';

    // 3. Vaciar la columna vieja (todos los bloqueos eran de prueba)
    $stmtPrev = $pdo->query("SELECT COUNT(*) FROM temas WHERE bloqueado_hasta IS NOT NULL");
    $cntPrev  = (int)$stmtPrev->fetchColumn();
    $pdo->exec("UPDATE temas SET bloqueado_hasta = NULL WHERE bloqueado_hasta IS NOT NULL");
    $pasos[] = "temas.bloqueado_hasta vaciado en {$cntPrev} filas (la columna queda sin uso, se puede borrar más adelante)";

    echo json_encode(['ok' => true, 'pasos' => $pasos], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'mensaje' => 'Error aplicando la migración',
        'detalle' => $e->getMessage(),
        'pasos'   => $pasos,
    ]);
}
