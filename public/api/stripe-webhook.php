<?php
// ─────────────────────────────────────────────────────────────
// api/stripe-webhook.php  —  Recibe eventos de Stripe
// Evento manejado: checkout.session.completed
// ─────────────────────────────────────────────────────────────

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/db-config.php';
require_once __DIR__ . '/log-helper.php';
require_once __DIR__ . '/email-helper.php';

header('Content-Type: application/json; charset=utf-8');

// ── Verificar firma ───────────────────────────────────────────
function verificarFirmaStripe(string $payload, string $sigHeader, string $secret): bool {
    if (!$sigHeader || !$secret) return false;
    $parts = [];
    foreach (explode(',', $sigHeader) as $part) {
        $kv = explode('=', $part, 2);
        if (count($kv) !== 2) continue;
        $parts[$kv[0]][] = $kv[1];
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

$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Sin secreto configurado → rechazar SIEMPRE. No procesar eventos sin firma verificada.
if (!defined('STRIPE_WEBHOOK_SECRET') || !STRIPE_WEBHOOK_SECRET) {
    error_log('stripe-webhook: STRIPE_WEBHOOK_SECRET no configurado — rechazando evento');
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Webhook no configurado']);
    exit;
}

if (!verificarFirmaStripe($payload, $sigHeader, STRIPE_WEBHOOK_SECRET)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Firma inválida']);
    exit;
}

try {

$evento = json_decode($payload, true);
if (!$evento || ($evento['type'] ?? '') !== 'checkout.session.completed') {
    // Evento válido pero no nos interesa: 200 para que Stripe no reintente
    http_response_code(200);
    echo json_encode(['ok' => true, 'msg' => 'evento ignorado']);
    exit;
}

$sesion    = $evento['data']['object'];
$sessionId = $sesion['id'];
$email     = strtolower(trim($sesion['customer_details']['email'] ?? $sesion['customer_email'] ?? ''));
$importe   = ($sesion['amount_total'] ?? 0) / 100;
$metadata  = $sesion['metadata'] ?? [];
$cursosIds = json_decode($metadata['cursos_ids'] ?? '[]', true);

// Nombre del alumno: prioridad custom_field "nombre", fallback derivado del email.
$nombreCustomField = '';
foreach ($sesion['custom_fields'] ?? [] as $cf) {
    if (($cf['key'] ?? '') === 'nombre') {
        $nombreCustomField = trim($cf['text']['value'] ?? '');
        break;
    }
}

if (!$email || empty($cursosIds)) {
    // Payload incompleto: 400 — no es recuperable por reintento
    error_log("stripe-webhook: payload incompleto session={$sessionId}");
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'email o cursos_ids vacíos']);
    exit;
}

$pdo = obtenerPDO();

// Evitar doble procesado (idempotencia)
$pagoRow = $pdo->prepare('SELECT id, estado, alumno_id FROM pagos WHERE stripe_session_id=:sid LIMIT 1');
$pagoRow->execute([':sid' => $sessionId]);
$pagoRow = $pagoRow->fetch();
if ($pagoRow && $pagoRow['estado'] === 'completado') {
    http_response_code(200);
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
    // Nombre: del custom_field si se rellenó, si no del email (fallback)
    $nombre        = $nombreCustomField !== '' ? $nombreCustomField : ucfirst(explode('@', $email)[0]);
    // fecha_baja = hoy + 1 año (acceso de 12 meses desde la compra)
    $pdo->prepare(
        'INSERT INTO usuarios (nombre, email, contrasena, rol, activo, fecha_alta, fecha_baja)
         VALUES (:n, :e, :h, "alumno", 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR))'
    )->execute([':n' => $nombre, ':e' => $email, ':h' => $hash]);
    $alumnoId = (int)$pdo->lastInsertId();
    $esNuevo  = true;
    registrar_log($pdo, 'alumno_creado_stripe', "Alumno {$nombre} ({$email}) creado tras pago Stripe", 0);
} else {
    $alumnoId = (int)$usuario['id'];
    // Para los emails: usa el nombre del custom_field si vino, si no el que ya tenía
    $nombre   = $nombreCustomField !== '' ? $nombreCustomField : $usuario['nombre'];
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

// ── Obtener URL del recibo de Stripe (para el email de confirmación) ─
$reciboUrl     = '';
$paymentIntent = $sesion['payment_intent'] ?? '';
if ($paymentIntent) {
    $ch = curl_init("https://api.stripe.com/v1/payment_intents/" . urlencode($paymentIntent) . "?expand[]=latest_charge");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200) {
        $piData    = json_decode($resp, true);
        $reciboUrl = $piData['latest_charge']['receipt_url'] ?? '';
    } else {
        error_log("stripe-webhook: no se pudo obtener recibo para PI {$paymentIntent} (HTTP {$httpCode})");
    }
}

// ── Enviar emails ─────────────────────────────────────────────
// Confirmación de compra: SIEMPRE (nuevos y existentes)
enviarEmailConfirmacionCompra($email, $nombre, $nombresCursos, $importe, $reciboUrl, $sessionId);

// Bienvenida con credenciales: SOLO si es un alumno nuevo
if ($esNuevo && $passwordPlano) {
    enviarEmailBienvenida($email, $nombre, $passwordPlano, $nombresCursos);
}

registrar_log($pdo, 'pago_completado', "Stripe {$sessionId} — {$email} — {$importe}€", 0);
http_response_code(200);
echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    // Error de servidor: 500 para que Stripe reintente (hasta ~3 días)
    error_log('stripe-webhook FATAL: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno']);
}
