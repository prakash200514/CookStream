<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) { header('Location: /cookstream/'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, name, email, password, is_verified FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || !password_verify($pass, $user['password'])) {
        $error = 'Invalid email or password.';
    } elseif (!$user['is_verified']) {
        $_SESSION['pending_email'] = $email;
        $error = 'Please verify your email first. <a href="/cookstream/auth/verify_otp.php">Verify now</a>';
    } else {
        setUserSession($user);
        $redirect = $_SESSION['redirect_after_login'] ?? '/cookstream/';
        unset($_SESSION['redirect_after_login']);
        header("Location: $redirect");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign In – CookStream</title>
<link rel="stylesheet" href="/cookstream/assets/css/style.css">
</head>
<body>
<div class="form-page">
  <div class="form-card">
    <h2>🍳 Welcome Back</h2>
    <p class="subtitle">Sign in to upload, like, and comment on food videos</p>

    <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" placeholder="you@example.com" required autofocus>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="Your password" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Sign In →</button>
    </form>
    <p class="form-footer">New to CookStream? <a href="/cookstream/auth/register.php">Create account</a></p>
  </div>
</div>
</body>
</html>
