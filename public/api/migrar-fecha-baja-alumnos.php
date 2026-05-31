<?php
// ─────────────────────────────────────────────────────────────
// api/migrar-fecha-baja-alumnos.php
// ONE-SHOT: rellena fecha_baja para alumnos creados por Stripe
// que quedaron con valor NULL (bug en stripe-webhook.php anterior
// al fix del 2026-05-31). Sin fecha_baja, el alumno tiene acceso
// ilimitado porque la comprobación de login y de requireAuth
// trata NULL como "sin expiración".
//
// Política: acceso de 1 año desde fecha_alta. Si fecha_alta es
// muy antigua y ese año ya pasó, el alumno queda expirado y la
// próxima petición autenticada o intento de login le bloqueará
// con el mensaje "Tu acceso ha expirado".
//
// Idempotente: solo actualiza filas con rol='alumno' y
// fecha_baja IS NULL. Las que ya tienen valor no se tocan.
//
// Modo dry-run: añadir { "dry_run": true } al body para ver
// qué se actualizaría SIN escribir nada en BD.
//
// Auth: BACKUP_SECRET por POST JSON, mismo patrón que las
// demás migraciones.
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

$dryRun = !empty($body['dry_run']);

$pdo = obtenerPDO();

// ── Localizar alumnos sin fecha_baja ─────────────────────────
$stmt = $pdo->query(
    "SELECT id, email, nombre, fecha_alta
     FROM usuarios
     WHERE rol = 'alumno' AND fecha_baja IS NULL"
);
$alumnos = $stmt->fetchAll();

$resultados = [];
$actualizados = 0;

foreach ($alumnos as $a) {
    $fechaAlta = $a['fecha_alta'];
    // Si no hay fecha_alta (caso anómalo), tomamos hoy como base.
    if (empty($fechaAlta)) {
        $fechaAlta = date('Y-m-d');
    }
    $fechaBaja = date('Y-m-d', strtotime($fechaAlta . ' +1 year'));

    $resultados[] = [
        'id'          => (int)$a['id'],
        'email'       => $a['email'],
        'nombre'      => $a['nombre'],
        'fecha_alta'  => $a['fecha_alta'],
        'fecha_baja_calculada' => $fechaBaja,
        'ya_expirado' => $fechaBaja < date('Y-m-d'),
    ];

    if (!$dryRun) {
        $upd = $pdo->prepare(
            'UPDATE usuarios SET fecha_baja = :fb WHERE id = :id AND fecha_baja IS NULL'
        );
        $upd->execute([':fb' => $fechaBaja, ':id' => $a['id']]);
        $actualizados += $upd->rowCount();
    }
}

echo json_encode([
    'ok'           => true,
    'dry_run'      => $dryRun,
    'encontrados'  => count($alumnos),
    'actualizados' => $dryRun ? 0 : $actualizados,
    'alumnos'      => $resultados,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
