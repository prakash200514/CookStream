<?php
// ─── Quick Email Test ─────────────────────────────────────────────────────────
// Visit: http://localhost/cookstream/test_mail.php
// DELETE THIS FILE after testing!

require_once __DIR__ . '/includes/mailer.php';

$testTo   = 'marimuthuprakash360@gmail.com'; // send test to yourself
$testName = 'Prakash';

$result = sendOTPEmail($testTo, $testName, '123456');

if ($result) {
    echo '<h2 style="color:green;font-family:sans-serif;">✅ Email sent successfully to ' . $testTo . '!</h2>';
    echo '<p style="font-family:sans-serif;">Check your Gmail inbox (and Spam folder).</p>';
} else {
    echo '<h2 style="color:red;font-family:sans-serif;">❌ Email failed to send.</h2>';
    echo '<p style="font-family:sans-serif;">Check your SMTP_PASS in mailer.php and ensure Gmail 2FA + App Password is configured.</p>';
    echo '<p style="font-family:sans-serif;">Also check: <code>C:\xampp\php\logs\php_error_log</code> for details.</p>';
}
?>
