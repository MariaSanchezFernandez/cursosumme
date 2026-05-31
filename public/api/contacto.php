<?php
// ─────────────────────────────────────────────────────────────
// api/contacto.php  —  Formulario de contacto público
// POST { nombre, email, asunto?, mensaje, hp? }
// Envía un email a info@cursosumme.es con los datos.
//
// Protecciones:
//   - Honeypot: el campo `hp` debe llegar vacío (los bots lo rellenan).
//   - Rate-limit por IP: 5 envíos cada 10 min.
//   - Validación básica de email y longitudes.
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-config.php';
require_once __DIR__ . '/email-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// Honeypot — si el campo oculto llega con contenido, descartamos
// silenciosamente (devolvemos 200 OK para no dar pistas al bot).
if (!empty($body['hp'] ?? '')) {
    echo json_encode(['ok' => true]);
    exit;
}

// Rate limit por IP (5 envíos cada 10 min).
if (function_exists('rateLimit')) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!rateLimit("contacto:$ip", 5, 600)) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'mensaje' => 'Demasiados intentos. Inténtalo de nuevo en unos minutos.']);
        exit;
    }
}

$nombre  = trim((string)($body['nombre']  ?? ''));
$email   = trim((string)($body['email']   ?? ''));
$asunto  = trim((string)($body['asunto']  ?? ''));
$mensaje = trim((string)($body['mensaje'] ?? ''));

if ($nombre === '' || mb_strlen($nombre) > 120) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Indica tu nombre (máx. 120 caracteres).']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Indica un email válido.']);
    exit;
}
if ($mensaje === '' || mb_strlen($mensaje) < 10 || mb_strlen($mensaje) > 3000) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'El mensaje debe tener entre 10 y 3000 caracteres.']);
    exit;
}
if (mb_strlen($asunto) > 160) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'El asunto es demasiado largo.']);
    exit;
}

$asuntoEmail = $asunto !== ''
    ? '[Contacto web] ' . $asunto
    : '[Contacto web] Nuevo mensaje de ' . $nombre;

$nombreEsc  = htmlspecialchars($nombre,  ENT_QUOTES, 'UTF-8');
$emailEsc   = htmlspecialchars($email,   ENT_QUOTES, 'UTF-8');
$asuntoEsc  = htmlspecialchars($asunto,  ENT_QUOTES, 'UTF-8');
$mensajeEsc = nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'));

$html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#1a1a1a;background:#f6faf8;padding:24px;">
  <div style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #e8eeec;border-radius:12px;padding:24px;">
    <h2 style="margin:0 0 16px;font-size:18px;color:#1a3a28;">Nuevo mensaje desde el formulario de contacto</h2>
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
      <tr><td style="padding:6px 0;color:#666;width:90px;">Nombre:</td><td><strong>{$nombreEsc}</strong></td></tr>
      <tr><td style="padding:6px 0;color:#666;">Email:</td><td><a href="mailto:{$emailEsc}">{$emailEsc}</a></td></tr>
      <tr><td style="padding:6px 0;color:#666;vertical-align:top;">Asunto:</td><td>{$asuntoEsc}</td></tr>
    </table>
    <hr style="border:none;border-top:1px solid #eef0ef;margin:16px 0;">
    <p style="font-size:14px;line-height:1.65;margin:0;white-space:pre-wrap;">{$mensajeEsc}</p>
  </div>
</body>
</html>
HTML;

$ok = _smtpEnviar('info@cursosumme.es', $asuntoEmail, $html);
if (!$ok) {
    error_log('contacto.php: fallo al enviar email');
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'No hemos podido enviar tu mensaje ahora mismo. Inténtalo de nuevo en unos minutos.']);
    exit;
}

echo json_encode(['ok' => true]);
