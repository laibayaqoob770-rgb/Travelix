<?php
/* ================================================================
   SMTP Email Sender — Gmail STARTTLS port 587
   Pure PHP, no library needed. Works on XAMPP/Windows.
================================================================ */

/**
 * @param array $attachments Optional list of ['filename'=>..., 'content'=>rawBytes, 'mime'=>'application/pdf']
 */
function travelixSendMail(string $to, string $subject, string $htmlBody, string &$errorOut = '', array $attachments = []): bool {
    $host = 'smtp.gmail.com';
    $port = 587;
    $user = MAIL_USER;
    $pass = MAIL_PASS;
    $from = MAIL_FROM;
    $name = MAIL_FROM_NAME;

    // Check OpenSSL is available (needed for STARTTLS)
    if (!function_exists('openssl_open')) {
        $errorOut = 'OpenSSL extension not loaded in PHP. Enable extension=openssl in php.ini';
        return false;
    }

    // Check app password is configured
    if ($pass === 'your_app_password_here' || empty($pass)) {
        $errorOut = 'Gmail app password not configured in config/mail_config.php';
        return false;
    }

    $errno = 0; $errstr = '';
    $socket = @fsockopen('tcp://' . $host, $port, $errno, $errstr, 15);
    if (!$socket) {
        $errorOut = "Cannot connect to smtp.gmail.com:587 — $errstr ($errno)";
        return false;
    }

    stream_set_timeout($socket, 15);

    $read = function() use ($socket, &$errorOut) {
        $resp = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') break; // end of multi-line response
        }
        return $resp;
    };

    $cmd = function(string $command) use ($socket, $read) {
        fputs($socket, $command . "\r\n");
        return $read();
    };

    // Greeting
    $r = $read();
    if (strpos($r, '220') === false) { fclose($socket); $errorOut = "No SMTP greeting: $r"; return false; }

    // EHLO
    $r = $cmd("EHLO localhost");
    if (strpos($r, '250') === false) { fclose($socket); $errorOut = "EHLO failed: $r"; return false; }

    // STARTTLS
    $r = $cmd("STARTTLS");
    if (strpos($r, '220') === false) { fclose($socket); $errorOut = "STARTTLS failed: $r"; return false; }

    // Upgrade to TLS
    $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    if (!$crypto) { fclose($socket); $errorOut = "TLS handshake failed — check OpenSSL in php.ini"; return false; }

    // EHLO again after TLS
    $r = $cmd("EHLO localhost");
    if (strpos($r, '250') === false) { fclose($socket); $errorOut = "EHLO after TLS failed: $r"; return false; }

    // AUTH LOGIN
    $r = $cmd("AUTH LOGIN");
    if (strpos($r, '334') === false) { fclose($socket); $errorOut = "AUTH LOGIN rejected: $r"; return false; }

    $r = $cmd(base64_encode($user));
    if (strpos($r, '334') === false) { fclose($socket); $errorOut = "Username rejected: $r"; return false; }

    $r = $cmd(base64_encode($pass));
    if (strpos($r, '235') === false) { fclose($socket); $errorOut = "Authentication failed (check app password): $r"; return false; }

    // MAIL FROM
    $r = $cmd("MAIL FROM:<{$from}>");
    if (strpos($r, '250') === false) { fclose($socket); $errorOut = "MAIL FROM failed: $r"; return false; }

    // RCPT TO
    $r = $cmd("RCPT TO:<{$to}>");
    if (strpos($r, '250') === false) { fclose($socket); $errorOut = "RCPT TO failed: $r"; return false; }

    // DATA
    $r = $cmd("DATA");
    if (strpos($r, '354') === false) { fclose($socket); $errorOut = "DATA command failed: $r"; return false; }

    // Send headers + body
    $msg  = "From: {$name} <{$from}>\r\n";
    $msg .= "To: {$to}\r\n";
    $msg .= "Subject: {$subject}\r\n";
    $msg .= "MIME-Version: 1.0\r\n";

    if ($attachments) {
        $boundary = 'travelix_' . bin2hex(random_bytes(12));
        $msg .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
        $msg .= "\r\n--{$boundary}\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $msg .= $htmlBody . "\r\n";

        foreach ($attachments as $att) {
            $filename = $att['filename'] ?? 'attachment.bin';
            $mime     = $att['mime'] ?? 'application/octet-stream';
            $content  = chunk_split(base64_encode((string)($att['content'] ?? '')));

            $msg .= "\r\n--{$boundary}\r\n";
            $msg .= "Content-Type: {$mime}; name=\"{$filename}\"\r\n";
            $msg .= "Content-Transfer-Encoding: base64\r\n";
            $msg .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
            $msg .= $content;
        }

        $msg .= "\r\n--{$boundary}--\r\n.";
    } else {
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "\r\n";
        $msg .= $htmlBody;
        $msg .= "\r\n.";
    }
    $r = $cmd($msg);
    if (strpos($r, '250') === false) { fclose($socket); $errorOut = "Message rejected: $r"; return false; }

    $cmd("QUIT");
    fclose($socket);
    return true;
}

function travelixResetCodeEmail(string $to, string $hotelName, string $code, int $minutes = 10): string {
    $hotelLine = $hotelName !== ''
        ? "for <b style=\"color:#0f3460;\">{$hotelName}</b>"
        : "for your Travelix Hotel Portal account";

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f4fa;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4fa;padding:40px 16px;">
  <tr><td align="center">
    <table width="520" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">

      <tr>
        <td style="background:linear-gradient(135deg,#0f3460,#1a5276);padding:32px 40px;text-align:center;">
          <div style="font-size:32px;margin-bottom:8px;">🔐</div>
          <h1 style="margin:0;color:#fff;font-size:22px;font-weight:800;">Password Reset Code</h1>
          <p style="margin:6px 0 0;color:rgba(255,255,255,.78);font-size:14px;">Travelix Hotel Portal</p>
        </td>
      </tr>

      <tr>
        <td style="padding:36px 40px;">
          <p style="margin:0 0 10px;font-size:15px;color:#1e293b;">Hello,</p>
          <p style="margin:0 0 24px;font-size:14px;color:#475569;line-height:1.7;">
            We received a request to reset the password {$hotelLine}. Enter the
            verification code below to continue.
          </p>

          <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8faff;border:2px solid #dce8ff;border-radius:14px;margin-bottom:24px;">
            <tr><td style="padding:28px;text-align:center;">
              <p style="margin:0 0 12px;font-size:12px;font-weight:800;color:#0f3460;text-transform:uppercase;letter-spacing:.5px;">Your Verification Code</p>
              <div style="font-size:38px;font-weight:900;color:#0f3460;letter-spacing:12px;font-family:monospace;">{$code}</div>
              <p style="margin:14px 0 0;font-size:12.5px;color:#94a3b8;">This code expires in {$minutes} minutes.</p>
            </td></tr>
          </table>

          <p style="margin:0;font-size:13px;color:#94a3b8;line-height:1.6;">
            If you did not request a password reset, you can safely ignore this email —
            your password will stay unchanged. Never share this code with anyone.
          </p>
        </td>
      </tr>

      <tr>
        <td style="background:#f8faff;padding:18px 40px;text-align:center;border-top:1px solid #e2e8f0;">
          <p style="margin:0;font-size:12px;color:#94a3b8;">&copy; Travelix &middot; Hotel Management Platform</p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

function travelixStaffCredentialEmail(string $to, string $hotelName, string $password, string $loginUrl): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f4fa;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4fa;padding:40px 16px;">
  <tr><td align="center">
    <table width="520" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">

      <tr>
        <td style="background:linear-gradient(135deg,#0f3460,#1a5276);padding:32px 40px;text-align:center;">
          <div style="font-size:32px;margin-bottom:8px;">🏨</div>
          <h1 style="margin:0;color:#fff;font-size:22px;font-weight:800;">Welcome to Travelix</h1>
          <p style="margin:6px 0 0;color:rgba(255,255,255,.78);font-size:14px;">Hotel Staff Portal Access</p>
        </td>
      </tr>

      <tr>
        <td style="padding:36px 40px;">
          <p style="margin:0 0 10px;font-size:15px;color:#1e293b;">Hello,</p>
          <p style="margin:0 0 24px;font-size:14px;color:#475569;line-height:1.7;">
            You have been assigned as a staff member for <b style="color:#0f3460;">{$hotelName}</b> on the Travelix platform.
            Use the credentials below to log in to the Hotel Staff Portal.
          </p>

          <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8faff;border:2px solid #dce8ff;border-radius:14px;margin-bottom:24px;">
            <tr><td style="padding:24px 28px;">
              <p style="margin:0 0 16px;font-size:12px;font-weight:800;color:#0f3460;text-transform:uppercase;letter-spacing:.5px;">Your Login Credentials</p>
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="padding:10px 0;border-bottom:1px solid #e2e8f0;">
                    <span style="font-size:11px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.4px;">Email Address</span><br>
                    <span style="font-size:16px;font-weight:700;color:#1e293b;">{$to}</span>
                  </td>
                </tr>
                <tr>
                  <td style="padding:16px 0 4px;">
                    <span style="font-size:11px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.4px;">Password</span><br>
                    <span style="display:inline-block;margin-top:8px;font-size:26px;font-weight:900;color:#0f3460;letter-spacing:5px;background:#e0f2fe;padding:12px 24px;border-radius:12px;font-family:monospace;">{$password}</span>
                  </td>
                </tr>
              </table>
            </td></tr>
          </table>

          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
            <tr><td align="center">
              <a href="{$loginUrl}"
                 style="display:inline-block;background:linear-gradient(135deg,#0f3460,#1a5276);color:#fff;text-decoration:none;padding:14px 36px;border-radius:12px;font-weight:800;font-size:15px;">
                Login to Hotel Portal &rarr;
              </a>
            </td></tr>
          </table>

          <p style="margin:0;font-size:13px;color:#94a3b8;line-height:1.6;">
            Please keep your credentials safe. If you have any issues, contact the Travelix administrator.
          </p>
        </td>
      </tr>

      <tr>
        <td style="background:#f8faff;padding:18px 40px;text-align:center;border-top:1px solid #e2e8f0;">
          <p style="margin:0;font-size:12px;color:#94a3b8;">&copy; Travelix &middot; Hotel Management Platform</p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}
