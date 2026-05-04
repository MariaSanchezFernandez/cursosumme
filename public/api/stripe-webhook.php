<?php
// ─────────────────────────────────────────────────────────────
// api/stripe-webhook.php  —  Recibe eventos de Stripe
// Evento manejado: checkout.session.completed
// ─────────────────────────────────────────────────────────────

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/db-config.php';
require_once __DIR__ . '/log-helper.php';
require_once __DIR__ . '/email-helper.php';

// Siempre responder 200 — si no, Stripe reintenta indefinidamente
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');

try {

$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// ── Verificar firma ───────────────────────────────────────────
function verificarFirmaStripe(string $payload, string $sigHeader, string $secret): bool {
    if (!$sigHeader || !$secret) return false;
    $parts = [];
    foreach (explode(',', $sigHeader) as $part) {
        [$k, $v] = explode('=', $part, 2);
        $parts[$k][] = $v;
    }
    $timestamp = $parts['t'][0] ?? '';
    $sigs      = $parts['v1'] ?? [];
    if (!$timestamp || empty($sigs)) return false;
    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($sigs as $sig) {
        if (hash_equals($expected, $sig)) return true;
    }
    return false;
}

if (STRIPE_WEBHOOK_SECRET && !verificarFirmaStripe($payload, $sigHeader, STRIPE_WEBHOOK_SECRET)) {
    echo json_encode(['ok' => false, 'error' => 'Firma inválida']);
    exit;
}

$evento = json_decode($payload, true);
if (!$evento || $evento['type'] !== 'checkout.session.completed') {
    echo json_encode(['ok' => true, 'msg' => 'evento ignorado']);
    exit;
}

$sesion    = $evento['data']['object'];
$sessionId = $sesion['id'];
$email     = strtolower(trim($sesion['customer_details']['email'] ?? $sesion['customer_email'] ?? ''));
$importe   = ($sesion['amount_total'] ?? 0) / 100;
$metadata  = $sesion['metadata'] ?? [];
$cursosIds = json_decode($metadata['cursos_ids'] ?? '[]', true);

if (!$email || empty($cursosIds)) {
    echo json_encode(['ok' => false, 'error' => 'email o cursos_ids vacíos']);
    exit;
}

$pdo = obtenerPDO();

// Evitar doble procesado
$pagoRow = $pdo->prepare('SELECT id, estado, alumno_id FROM pagos WHERE stripe_session_id=:sid LIMIT 1');
$pagoRow->execute([':sid' => $sessionId]);
$pagoRow = $pagoRow->fetch();
if ($pagoRow && $pagoRow['estado'] === 'completado') {
    echo json_encode(['ok' => true, 'msg' => 'ya procesado']);
    exit;
}

// ── Generar contraseña ────────────────────────────────────────
function generarPassword(int $len = 12): string {
    $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $pwd   = '';
    for ($i = 0; $i < $len; $i++) $pwd .= $chars[random_int(0, strlen($chars) - 1)];
    return $pwd;
}

// ── Buscar o crear alumno ─────────────────────────────────────
$uStmt = $pdo->prepare('SELECT id, nombre FROM usuarios WHERE email=:email LIMIT 1');
$uStmt->execute([':email' => $email]);
$usuario = $uStmt->fetch();

$passwordPlano = null;
$esNuevo       = false;

if (!$usuario) {
    $passwordPlano = generarPassword();
    $hash          = password_hash($passwordPlano, PASSWORD_BCRYPT, ['cost' => 12]);
    $nombre        = ucfirst(explode('@', $email)[0]);
    $pdo->prepare('INSERT INTO usuarios (nombre, email, contrasena, rol, activo) VALUES (:n, :e, :h, "alumno", 1)')
        ->execute([':n' => $nombre, ':e' => $email, ':h' => $hash]);
    $alumnoId = (int)$pdo->lastInsertId();
    $esNuevo  = true;
    registrar_log($pdo, 'alumno_creado_stripe', "Alumno {$email} creado tras pago Stripe", 0);
} else {
    $alumnoId = (int)$usuario['id'];
    $nombre   = $usuario['nombre'];
}

// ── Asignar cursos ────────────────────────────────────────────
foreach ($cursosIds as $cursoId) {
    $cursoId = (int)$cursoId;
    $existe  = $pdo->prepare('SELECT 1 FROM usuarios_cursos WHERE usuario_id=:u AND curso_id=:c LIMIT 1');
    $existe->execute([':u' => $alumnoId, ':c' => $cursoId]);
    if (!$existe->fetchColumn()) {
        $pdo->prepare('INSERT INTO usuarios_cursos (usuario_id, curso_id) VALUES (:u, :c)')
            ->execute([':u' => $alumnoId, ':c' => $cursoId]);
    }
}

// Nombres de cursos para el email
$ph    = implode(',', array_fill(0, count($cursosIds), '?'));
$cStmt = $pdo->prepare("SELECT titulo FROM cursos WHERE id IN ({$ph})");
$cStmt->execute(array_map('intval', $cursosIds));
$nombresCursos = array_column($cStmt->fetchAll(), 'titulo');

// ── Registrar pago ────────────────────────────────────────────
if ($pagoRow) {
    $pdo->prepare('UPDATE pagos SET estado="completado", email=:email, alumno_id=:aid, importe=:imp WHERE stripe_session_id=:sid')
        ->execute([':email' => $email, ':aid' => $alumnoId, ':imp' => $importe, ':sid' => $sessionId]);
} else {
    $pdo->prepare('INSERT INTO pagos (stripe_session_id, email, cursos_ids, alumno_id, estado, importe) VALUES (:sid, :email, :cids, :aid, "completado", :imp)')
        ->execute([':sid' => $sessionId, ':email' => $email, ':cids' => json_encode($cursosIds), ':aid' => $alumnoId, ':imp' => $importe]);
}

// ── Enviar email ──────────────────────────────────────────────
if ($esNuevo && $passwordPlano) {
    enviarEmailBienvenida($email, $nombre, $passwordPlano, $nombresCursos);
}

registrar_log($pdo, 'pago_completado', "Stripe {$sessionId} — {$email} — {$importe}€", 0);
echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    error_log('stripe-webhook FATAL: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
