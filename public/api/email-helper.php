<?php
// ─────────────────────────────────────────────────────────────
// api/email-helper.php  —  Envío de email vía curl SMTP (IONOS)
// ─────────────────────────────────────────────────────────────

function enviarEmailBienvenida(string $para, string $nombreAlumno, string $password, array $cursos): bool {
    $html = construirHtmlBienvenida($para, $nombreAlumno, $password, $cursos);
    return _smtpEnviar($para, '¡Ya tienes acceso a tus cursos! – Cursos Umme', $html);
}

function enviarEmailConfirmacionCompra(
    string $para,
    string $nombreAlumno,
    array $cursos,
    float $importe,
    string $reciboUrl,
    string $sessionId
): bool {
    $html = construirHtmlConfirmacionCompra($para, $nombreAlumno, $cursos, $importe, $reciboUrl, $sessionId);
    return _smtpEnviar($para, 'Confirmación de tu compra – Cursos Umme', $html);
}

function construirHtmlBienvenida(string $para, string $nombreAlumno, string $password, array $cursos): string {
    // Tokens de diseño (coinciden con src/styles/global.css)
    $cPrimary  = '#b3e8da';  // verde menta
    $cLight    = '#def1ec';  // fondo light
    $cCoral    = '#ffb5a7';  // CTA
    $cText     = '#000000';
    $cMuted    = '#555555';
    $cBorder   = '#e8e8e8';
    $cBg       = '#f6faf8';

    $listaCursos = '';
    foreach ($cursos as $c) {
        $listaCursos .= "
          <tr>
            <td style='padding:10px 0;border-bottom:1px solid {$cBorder};vertical-align:top;'>
              <span style='display:inline-block;width:6px;height:6px;background:{$cPrimary};border-radius:50%;margin:6px 12px 0 0;vertical-align:top;'></span>
              <span style='font-size:15px;color:{$cText};font-weight:600;'>" . htmlspecialchars($c) . "</span>
            </td>
          </tr>";
    }

    $emailEsc    = htmlspecialchars($para);
    $passEsc     = htmlspecialchars($password);
    $nombreEsc   = htmlspecialchars($nombreAlumno);
    $anio        = date('Y');

    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bienvenida a Cursos Umme</title>
  <!--[if mso]><style>* { font-family: Arial, sans-serif !important; }</style><![endif]-->
</head>
<body style="margin:0;padding:0;background:{$cBg};font-family:'DM Sans','Helvetica Neue',Arial,sans-serif;color:{$cText};-webkit-font-smoothing:antialiased;">

  <!-- Preheader (texto preview que muestran los clientes antes de abrir) -->
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:{$cBg};">
    Tu pago se ha procesado. Aquí tienes tus credenciales de acceso a Cursos Umme.
  </div>

  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:{$cBg};">
    <tr>
      <td align="center" style="padding:32px 16px;">

        <!-- Contenedor principal -->
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid {$cBorder};">

          <!-- Cabecera con logo -->
          <tr>
            <td style="background:{$cLight};padding:28px 40px;text-align:center;">
              <div style="font-family:'DM Sans','Helvetica Neue',Arial,sans-serif;font-size:18px;letter-spacing:0.04em;">
                <span style="font-weight:400;color:{$cMuted};">cursos</span><span style="font-weight:800;color:{$cText};">Umme</span>
              </div>
            </td>
          </tr>

          <!-- Acento -->
          <tr><td style="height:5px;background:{$cPrimary};line-height:5px;font-size:0;">&nbsp;</td></tr>

          <!-- Cuerpo -->
          <tr>
            <td style="padding:40px 40px 32px;">

              <h1 style="margin:0 0 24px;font-size:26px;font-weight:800;color:{$cText};letter-spacing:-0.02em;line-height:1.2;">
                ¡Bienvenida!
              </h1>

              <p style="margin:0 0 12px;font-size:16px;color:{$cText};line-height:1.5;">
                Hola <strong>{$nombreEsc}</strong>,
              </p>
              <p style="margin:0 0 28px;font-size:15px;color:{$cMuted};line-height:1.65;">
                Tu pago se ha procesado correctamente. Ya tienes acceso a:
              </p>

              <!-- Lista de cursos -->
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 32px;">
                {$listaCursos}
              </table>

              <!-- Credenciales -->
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:{$cLight};border-radius:10px;margin:0 0 32px;">
                <tr>
                  <td style="padding:22px 24px;">
                    <p style="margin:0 0 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.12em;color:{$cMuted};">
                      Tus credenciales de acceso
                    </p>
                    <p style="margin:0 0 8px;font-size:14px;color:{$cText};">
                      <span style="color:{$cMuted};">Usuario</span><br>
                      <strong style="font-size:15px;">{$emailEsc}</strong>
                    </p>
                    <p style="margin:14px 0 0;font-size:14px;color:{$cText};">
                      <span style="color:{$cMuted};">Contraseña</span><br>
                      <code style="display:inline-block;background:#ffffff;border:1px solid {$cBorder};padding:6px 12px;border-radius:6px;font-family:'SF Mono',Menlo,Consolas,monospace;font-size:14px;font-weight:700;color:{$cText};margin-top:4px;">{$passEsc}</code>
                    </p>
                  </td>
                </tr>
              </table>

              <!-- CTA -->
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                  <td align="center" style="padding:0 0 24px;">
                    <a href="https://cursosumme.es" style="display:inline-block;background:{$cCoral};color:{$cText};text-decoration:none;padding:14px 36px;border-radius:8px;font-family:'DM Sans','Helvetica Neue',Arial,sans-serif;font-weight:800;font-size:15px;letter-spacing:0.01em;">
                      Acceder a mis cursos &nbsp;→
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin:8px 0 0;font-size:13px;color:#888;text-align:center;line-height:1.5;">
                Te recomendamos cambiar la contraseña desde tu perfil tras el primer acceso.
              </p>
            </td>
          </tr>
        </table>

        <!-- Footer fuera del contenedor blanco -->
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;margin-top:20px;">
          <tr>
            <td style="text-align:center;padding:8px 16px;">
              <p style="margin:0;font-size:12px;color:#a0a0a0;line-height:1.6;">
                © {$anio} Cursos Umme &nbsp;·&nbsp; <a href="mailto:facturacion@cursosumme.es" style="color:#a0a0a0;text-decoration:none;">facturacion@cursosumme.es</a>
              </p>
            </td>
          </tr>
        </table>

      </td>
    </tr>
  </table>
</body>
</html>
HTML;

    return $html;
}

function construirHtmlConfirmacionCompra(
    string $para,
    string $nombreAlumno,
    array $cursos,
    float $importe,
    string $reciboUrl,
    string $sessionId
): string {
    // Mismos tokens de diseño que el email de bienvenida
    $cPrimary  = '#b3e8da';
    $cLight    = '#def1ec';
    $cCoral    = '#ffb5a7';
    $cText     = '#000000';
    $cMuted    = '#555555';
    $cBorder   = '#e8e8e8';
    $cBg       = '#f6faf8';

    $listaCursos = '';
    foreach ($cursos as $c) {
        $listaCursos .= "
          <tr>
            <td style='padding:10px 0;border-bottom:1px solid {$cBorder};vertical-align:top;'>
              <span style='display:inline-block;width:6px;height:6px;background:{$cPrimary};border-radius:50%;margin:6px 12px 0 0;vertical-align:top;'></span>
              <span style='font-size:15px;color:{$cText};font-weight:600;'>" . htmlspecialchars($c) . "</span>
            </td>
          </tr>";
    }

    $nombreEsc   = htmlspecialchars($nombreAlumno);
    $importeFmt  = number_format($importe, 2, ',', '.');
    $fecha       = date('d/m/Y');
    $refCorta    = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $sessionId), -10));
    $anio        = date('Y');

    // Botón "Ver recibo" — solo si tenemos URL
    $ctaRecibo = '';
    if ($reciboUrl !== '') {
        $reciboUrlEsc = htmlspecialchars($reciboUrl);
        $ctaRecibo = <<<HTML
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                  <td align="center" style="padding:8px 0 24px;">
                    <a href="{$reciboUrlEsc}" style="display:inline-block;background:{$cCoral};color:{$cText};text-decoration:none;padding:14px 36px;border-radius:8px;font-family:'DM Sans','Helvetica Neue',Arial,sans-serif;font-weight:800;font-size:15px;letter-spacing:0.01em;">
                      Ver recibo &nbsp;→
                    </a>
                  </td>
                </tr>
              </table>
HTML;
    }

    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Confirmación de compra – Cursos Umme</title>
  <!--[if mso]><style>* { font-family: Arial, sans-serif !important; }</style><![endif]-->
</head>
<body style="margin:0;padding:0;background:{$cBg};font-family:'DM Sans','Helvetica Neue',Arial,sans-serif;color:{$cText};-webkit-font-smoothing:antialiased;">

  <div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:{$cBg};">
    Hemos recibido tu pago de {$importeFmt} €. Aquí tienes los detalles y tu recibo.
  </div>

  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:{$cBg};">
    <tr>
      <td align="center" style="padding:32px 16px;">

        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid {$cBorder};">

          <!-- Cabecera con logo -->
          <tr>
            <td style="background:{$cLight};padding:28px 40px;text-align:center;">
              <div style="font-family:'DM Sans','Helvetica Neue',Arial,sans-serif;font-size:18px;letter-spacing:0.04em;">
                <span style="font-weight:400;color:{$cMuted};">cursos</span><span style="font-weight:800;color:{$cText};">Umme</span>
              </div>
            </td>
          </tr>

          <tr><td style="height:5px;background:{$cPrimary};line-height:5px;font-size:0;">&nbsp;</td></tr>

          <!-- Cuerpo -->
          <tr>
            <td style="padding:40px 40px 32px;">

              <h1 style="margin:0 0 24px;font-size:26px;font-weight:800;color:{$cText};letter-spacing:-0.02em;line-height:1.2;">
                Compra confirmada
              </h1>

              <p style="margin:0 0 12px;font-size:16px;color:{$cText};line-height:1.5;">
                Hola <strong>{$nombreEsc}</strong>,
              </p>
              <p style="margin:0 0 28px;font-size:15px;color:{$cMuted};line-height:1.65;">
                Hemos recibido tu pago correctamente. Estos son los detalles de tu compra:
              </p>

              <!-- Resumen del pedido -->
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:{$cLight};border-radius:10px;margin:0 0 28px;">
                <tr>
                  <td style="padding:22px 24px;">
                    <p style="margin:0 0 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.12em;color:{$cMuted};">
                      Resumen del pedido
                    </p>

                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 16px;">
                      {$listaCursos}
                    </table>

                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:8px;">
                      <tr>
                        <td style="font-size:14px;color:{$cMuted};padding:4px 0;">Fecha</td>
                        <td align="right" style="font-size:14px;color:{$cText};padding:4px 0;font-weight:600;">{$fecha}</td>
                      </tr>
                      <tr>
                        <td style="font-size:14px;color:{$cMuted};padding:4px 0;">Referencia</td>
                        <td align="right" style="font-size:13px;color:{$cText};padding:4px 0;font-family:'SF Mono',Menlo,Consolas,monospace;">{$refCorta}</td>
                      </tr>
                      <tr>
                        <td colspan="2" style="border-top:1px solid {$cBorder};padding:0;line-height:0;font-size:0;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style="font-size:15px;color:{$cText};padding:12px 0 0;font-weight:700;">Total</td>
                        <td align="right" style="font-size:18px;color:{$cText};padding:12px 0 0;font-weight:800;">{$importeFmt} €</td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              {$ctaRecibo}

              <p style="margin:8px 0 0;font-size:13px;color:#888;text-align:center;line-height:1.5;">
                Si tienes cualquier duda, escríbenos a <a href="mailto:facturacion@cursosumme.es" style="color:#888;text-decoration:underline;">facturacion@cursosumme.es</a>.
              </p>
            </td>
          </tr>
        </table>

        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;margin-top:20px;">
          <tr>
            <td style="text-align:center;padding:8px 16px;">
              <p style="margin:0;font-size:12px;color:#a0a0a0;line-height:1.6;">
                © {$anio} Cursos Umme &nbsp;·&nbsp; <a href="mailto:facturacion@cursosumme.es" style="color:#a0a0a0;text-decoration:none;">facturacion@cursosumme.es</a>
              </p>
            </td>
          </tr>
        </table>

      </td>
    </tr>
  </table>
</body>
</html>
HTML;

    return $html;
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
