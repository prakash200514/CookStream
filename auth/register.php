<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

if (isLoggedIn()) { header('Location: /cookstream/'); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = sanitize($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (!$name || !$email || !$pass) {
        $error = 'All fields are required.';
    } elseif (!isValidEmail($email)) {
        $error = 'Invalid email address.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } else {
        // Check duplicate
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'An account with this email already exists.';
        } else {
            $otp     = generateOTP();
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $hashed  = password_hash($pass, PASSWORD_BCRYPT);

            $stmt = $conn->prepare("INSERT INTO users (name,email,password,otp,otp_expires_at) VALUES (?,?,?,?,?)");
            $stmt->bind_param('sssss', $name, $email, $hashed, $otp, $expires);
            if ($stmt->execute()) {
                sendOTPEmail($email, $name, $otp);
                $_SESSION['pending_email'] = $email;
                header('Location: /cookstream/auth/verify_otp.php');
                exit;
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Register – CookStream</title>
<link rel="stylesheet" href="/cookstream/assets/css/style.css">
</head>
<body>
<div class="form-page">
  <div class="form-card">
    <h2>🍳 Join CookStream</h2>
    <p class="subtitle">Create your free account to upload and engage with food videos</p>

    <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" placeholder="Your name" value="<?= sanitize($_POST['name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" placeholder="you@example.com" value="<?= sanitize($_POST['email'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="Min. 6 characters" required>
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="password2" placeholder="Repeat password" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
        Create Account →
      </button>
    </form>
    <p class="form-footer">Already have an account? <a href="/cookstream/auth/login.php">Sign In</a></p>
  </div>
</div>
</body>
</html>
