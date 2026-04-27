<?php
// ── OTP Debug Tool ── DELETE THIS FILE AFTER TESTING ──────────────────────
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/mailer.php';

$email = $_SESSION['pending_email'] ?? ($_GET['email'] ?? '');

echo "<h2>CookStream – OTP Debug</h2>";

if ($email) {
    echo "<p><strong>Checking email:</strong> " . htmlspecialchars($email) . "</p>";

    $stmt = $conn->prepare("SELECT id, name, otp, otp_expires_at, is_verified FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        echo "<p style='color:red'>❌ No user found with this email.</p>";
    } else {
        echo "<table border='1' cellpadding='8'>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        foreach ($user as $k => $v) {
            echo "<tr><td>$k</td><td>" . htmlspecialchars((string)$v) . "</td></tr>";
        }
        echo "</table>";

        if ($user['otp']) {
            echo "<p>⏰ OTP expires at: <strong>" . $user['otp_expires_at'] . "</strong></p>";
            $expired = strtotime($user['otp_expires_at']) < time();
            echo "<p>" . ($expired ? "❌ OTP is <strong>EXPIRED</strong>" : "✅ OTP is still <strong>VALID</strong>") . "</p>";
        } else {
            echo "<p style='color:orange'>⚠️ OTP is NULL in database (already used or cleared).</p>";
        }
    }
} else {
    echo "<p style='color:orange'>No pending_email in session. Pass ?email=you@example.com to check.</p>";
}

// Test mail sending
if (isset($_GET['sendtest'])) {
    $testEmail = $_GET['email'] ?? $email;
    $result = sendOTPEmail($testEmail, 'Test User', '123456');
    echo "<hr>";
    echo "<h3>Mail Send Test</h3>";
    echo $result
        ? "<p style='color:green'>✅ Test email sent successfully to $testEmail</p>"
        : "<p style='color:red'>❌ Mail send FAILED – check PHP error log (xampp/apache/logs/error.log)</p>";
}

echo "<hr><p><a href='?email=" . urlencode($email) . "&sendtest=1'>📧 Send test OTP email</a></p>";
echo "<p style='color:gray;font-size:12px'>DELETE this file before going live!</p>";
