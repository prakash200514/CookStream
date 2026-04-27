<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

if (isLoggedIn()) { header('Location: /cookstream/'); exit; }

$email = $_SESSION['pending_email'] ?? '';
if (!$email) { header('Location: /cookstream/auth/register.php'); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered = trim($_POST['otp'] ?? '');

    $stmt = $conn->prepare("SELECT id, name, otp, otp_expires_at FROM users WHERE email = ? AND is_verified = 0");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        $error = 'Account not found or already verified.';
    } elseif ($user['otp'] !== $entered) {
        $error = 'Incorrect OTP. Please try again.';
    } elseif (strtotime($user['otp_expires_at']) < time()) {
        $error = 'OTP has expired. Please request a new one.';
    } else {
        $upd = $conn->prepare("UPDATE users SET is_verified=1, otp=NULL, otp_expires_at=NULL WHERE id=?");
        $upd->bind_param('i', $user['id']);
        $upd->execute();
        $success = 'Email verified! Redirecting to login…';
        unset($_SESSION['pending_email']);
        header("Refresh: 2; url=/cookstream/auth/login.php");
    }
}

// Resend OTP
if (isset($_GET['resend']) && $email) {
    $otp     = generateOTP();
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $stmt = $conn->prepare("UPDATE users SET otp=?, otp_expires_at=? WHERE email=? AND is_verified=0");
    $stmt->bind_param('sss', $otp, $expires, $email);
    $stmt->execute();
    $row = $conn->query("SELECT name FROM users WHERE email='".mysqli_real_escape_string($conn,$email)."' LIMIT 1")->fetch_assoc();
    sendOTPEmail($email, $row['name'] ?? 'User', $otp);
    $success = 'New OTP sent to your email!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verify Email – CookStream</title>
<link rel="stylesheet" href="/cookstream/assets/css/style.css">
</head>
<body>
<div class="form-page">
  <div class="form-card" style="text-align:center">
    <h2>📧 Verify Email</h2>
    <p class="subtitle">Enter the 6-digit code sent to <strong style="color:var(--accent2)"><?= sanitize($email) ?></strong></p>

    <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

    <form method="POST" id="otp-form">
      <div class="otp-inputs">
        <?php for ($i = 0; $i < 6; $i++): ?>
          <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" class="otp-box">
        <?php endfor; ?>
        <input type="hidden" name="otp" id="otp-hidden">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Verify →</button>
    </form>
    <script>
    (function(){
      const boxes = document.querySelectorAll('.otp-box');
      const hidden = document.getElementById('otp-hidden');
      function sync(){ hidden.value = [...boxes].map(b=>b.value).join(''); }
      boxes.forEach((box, i) => {
        box.addEventListener('input', () => {
          box.value = box.value.replace(/\D/,'').slice(-1);
          sync();
          if (box.value && i < boxes.length - 1) boxes[i+1].focus();
        });
        box.addEventListener('keydown', e => {
          if (e.key === 'Backspace' && !box.value && i > 0) { boxes[i-1].focus(); sync(); }
        });
        box.addEventListener('paste', e => {
          const txt = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');
          boxes.forEach((b,j) => b.value = txt[j]||'');
          sync();
          e.preventDefault();
        });
      });
      document.getElementById('otp-form').addEventListener('submit', () => sync());
      if (boxes.length) boxes[0].focus();
    })();
    </script>
    <p class="form-footer" style="margin-top:16px">
      Didn't receive it? <a href="?resend=1">Resend OTP</a>
    </p>
  </div>
</div>
<script src="/cookstream/assets/js/main.js"></script>
</body>
</html>
