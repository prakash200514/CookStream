<?php
// ─── CookStream SMTP Mailer ───────────────────────────────────────────────────
// Uses PHP's native socket-level SMTP (no external library needed)
// Configure your Gmail App Password below:

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_email@gmail.com');      // ← Replace with your Gmail
define('SMTP_PASS', 'your_app_password_here');    // ← Replace with Gmail App Password
define('SMTP_FROM', 'your_email@gmail.com');      // ← Same as SMTP_USER
define('SMTP_NAME', 'CookStream');

/**
 * Send an email via Gmail SMTP (TLS/STARTTLS)
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    $host    = SMTP_HOST;
    $port    = SMTP_PORT;
    $user    = SMTP_USER;
    $pass    = SMTP_PASS;
    $from    = SMTP_FROM;
    $fromName = SMTP_NAME;

    // MIME boundary
    $boundary = md5(uniqid((string)time()));

    // Build raw message
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

    $plain = strip_tags($htmlBody);

    $body  = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n$plain\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n$htmlBody\r\n";
    $body .= "--$boundary--";

    try {
        // Connect
        $sock = @fsockopen("tcp://$host", $port, $errno, $errstr, 15);
        if (!$sock) throw new Exception("Cannot connect: $errstr ($errno)");

        $read = function() use ($sock) {
            $out = '';
            while (!feof($sock)) {
                $line = fgets($sock, 515);
                $out .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            return $out;
        };

        $send = function(string $cmd) use ($sock, $read) {
            fputs($sock, $cmd . "\r\n");
            return $read();
        };

        $read(); // banner

        $send("EHLO localhost");

        // STARTTLS
        $send("STARTTLS");
        stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

        $send("EHLO localhost");
        $send("AUTH LOGIN");
        $send(base64_encode($user));
        $send(base64_encode($pass));

        $send("MAIL FROM:<$from>");
        $send("RCPT TO:<$toEmail>");
        $send("DATA");

        $msg  = "From: \"$fromName\" <$from>\r\n";
        $msg .= "To: \"$toName\" <$toEmail>\r\n";
        $msg .= "Subject: $subject\r\n";
        $msg .= "Date: " . date('r') . "\r\n";
        $msg .= $headers . "\r\n" . $body;

        fputs($sock, $msg . "\r\n.\r\n");
        $read();

        $send("QUIT");
        fclose($sock);
        return true;

    } catch (Exception $e) {
        error_log('[CookStream Mailer] ' . $e->getMessage());
        return false;
    }
}

/**
 * Send OTP verification email
 */
function sendOTPEmail(string $toEmail, string $toName, string $otp): bool {
    $subject = 'CookStream – Your Email Verification Code';
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#111;font-family:Inter,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr><td align="center" style="padding:40px 20px;">
      <table width="500" style="background:#1a1a2e;border-radius:16px;overflow:hidden;border:1px solid #ff6b35;">
        <tr>
          <td style="background:linear-gradient(135deg,#ff6b35,#f7c59f);padding:30px;text-align:center;">
            <h1 style="margin:0;color:#fff;font-size:28px;letter-spacing:2px;">🍳 CookStream</h1>
            <p style="margin:8px 0 0;color:rgba(255,255,255,0.85);font-size:14px;">Food Video Platform</p>
          </td>
        </tr>
        <tr>
          <td style="padding:40px 30px;text-align:center;">
            <h2 style="color:#f7c59f;margin:0 0 12px;">Verify Your Email</h2>
            <p style="color:#aaa;font-size:15px;margin:0 0 30px;">Hello <strong style="color:#fff;">$toName</strong>, use the code below to verify your account.</p>
            <div style="background:#0d0d0d;border:2px solid #ff6b35;border-radius:12px;padding:24px;display:inline-block;margin:0 auto;">
              <span style="font-size:42px;font-weight:700;letter-spacing:12px;color:#ff6b35;">$otp</span>
            </div>
            <p style="color:#666;font-size:13px;margin:24px 0 0;">This code expires in <strong style="color:#f7c59f;">10 minutes</strong>.</p>
            <p style="color:#666;font-size:12px;margin:8px 0 0;">If you didn't request this, please ignore this email.</p>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 30px;background:#0d0d0d;text-align:center;">
            <p style="color:#555;font-size:12px;margin:0;">© 2025 CookStream · All rights reserved</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    return sendMail($toEmail, $toName, $subject, $html);
}
?>
