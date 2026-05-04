<?php
// ─────────────────────────────────────────────────────────────
// api/email-helper.php  —  Envío de email vía curl SMTP (IONOS)
// ─────────────────────────────────────────────────────────────

function enviarEmailBienvenida(string $para, string $nombreAlumno, string $password, array $cursos): bool {
    $listaCursos = '';
    foreach ($cursos as $c) {
        $listaCursos .= "<li style='margin-bottom:4px;'>📚 " . htmlspecialchars($c) . "</li>";
    }

    $html = "<!DOCTYPE html>
<html lang='es'>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#f4f7f6;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);'>
    <div style='background:#2e7d55;padding:32px 40px;text-align:center;'>
      <h1 style='margin:0;color:#fff;font-size:1.5rem;'>¡Bienvenida a Cursos Umme!</h1>
    </div>
    <div style='padding:32px 40px;'>
      <p style='margin:0 0 16px;color:#333;font-size:0.95rem;'>
        Hola <strong>" . htmlspecialchars($nombreAlumno) . "</strong>,
      </p>
      <p style='margin:0 0 16px;color:#555;font-size:0.9rem;'>
        Tu pago se ha procesado correctamente. Ya tienes acceso a:
      </p>
      <ul style='margin:0 0 24px;padding-left:20px;color:#333;font-size:0.9rem;line-height:1.8;'>
        {$listaCursos}
      </ul>
      <div style='background:#f0f8f4;border-radius:8px;padding:20px 24px;margin-bottom:24px;'>
        <p style='margin:0 0 8px;font-size:0.8rem;color:#888;text-transform:uppercase;letter-spacing:0.05em;font-weight:700;'>Tus credenciales de acceso</p>
        <p style='margin:0 0 6px;font-size:0.9rem;color:#333;'><strong>Usuario:</strong> " . htmlspecialchars($para) . "</p>
        <p style='margin:0;font-size:0.9rem;color:#333;'><strong>Contraseña:</strong> <code style='background:#e8f4ee;padding:2px 8px;border-radius:4px;font-family:monospace;'>" . htmlspecialchars($password) . "</code></p>
      </div>
      <div style='text-align:center;margin-bottom:24px;'>
        <a href='https://cursosumme.es' style='display:inline-block;background:#2e7d55;color:#fff;text-decoration:none;padding:12px 32px;border-radius:8px;font-weight:700;font-size:0.95rem;'>
          Acceder a mis cursos →
        </a>
      </div>
      <p style='margin:0;font-size:0.8rem;color:#aaa;text-align:center;'>
        Te recomendamos cambiar tu contraseña desde tu perfil tras el primer acceso.
      </p>
    </div>
    <div style='background:#f9fbfa;padding:16px 40px;text-align:center;border-top:1px solid #e8eeec;'>
      <p style='margin:0;font-size:0.75rem;color:#bbb;'>© 2025 Cursos Umme · facturacion@cursosumme.es</p>
    </div>
  </div>
</body>
</html>";

    return _smtpEnviar($para, '¡Ya tienes acceso a tus cursos! – Cursos Umme', $html);
}

function _smtpEnviar(string $para, string $asunto, string $html): bool {
    $from     = SMTP_USER;
    $fromName = SMTP_FROM_NAME;
    $host     = SMTP_HOST;
    $port     = SMTP_PORT;

    $boundary = md5(uniqid());
    $plain    = strip_tags(str_replace(['<br>', '<br/>', '</p>', '</li>', '</div>'], "\n", $html));

    $msg  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
    $msg .= "To: {$para}\r\n";
    $msg .= "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $msg .= "Date: " . date('r') . "\r\n";
    $msg .= "\r\n";
    $msg .= "--{$boundary}\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $msg .= $plain . "\r\n";
    $msg .= "--{$boundary}\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $msg .= $html . "\r\n";
    $msg .= "--{$boundary}--\r\n";

    // Escribir mensaje en fichero temporal para curl
    $tmp = tmpfile();
    if (!$tmp) return false;
    fwrite($tmp, $msg);
    rewind($tmp);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => "smtp://{$host}:{$port}",
        CURLOPT_MAIL_FROM      => "<{$from}>",
        CURLOPT_MAIL_RCPT      => ["<{$para}>"],
        CURLOPT_READDATA       => $tmp,
        CURLOPT_UPLOAD         => true,
        CURLOPT_INFILESIZE     => strlen($msg),
        CURLOPT_USE_SSL        => CURLUSESSL_ALL,
        CURLOPT_USERNAME       => SMTP_USER,
        CURLOPT_PASSWORD       => SMTP_PASS,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_VERBOSE        => false,
    ]);

    $result = curl_exec($ch);
    $error  = curl_error($ch);
    curl_close($ch);
    fclose($tmp);

    if ($error) {
        error_log("email-helper SMTP error: {$error}");
        return false;
    }
    return true;
}
