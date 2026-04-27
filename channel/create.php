<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

// If user already has a channel, redirect to dashboard
$existing = getUserChannel($conn);
if ($existing) {
    header('Location: /cookstream/channel/dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if (strlen($name) < 3) {
        $error = 'Channel name must be at least 3 characters.';
    } else {
        // Handle optional banner upload
        $bannerPath = null;
        if (!empty($_FILES['banner']['name'])) {
            $ext     = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','gif'];
            if (!in_array($ext, $allowed)) {
                $error = 'Banner must be an image (jpg, png, webp, gif).';
            } elseif ($_FILES['banner']['size'] > 5 * 1024 * 1024) {
                $error = 'Banner image must be under 5 MB.';
            } else {
                $bannerName = 'banner_' . uniqid() . '.' . $ext;
                $dest       = UPLOAD_DIR . $bannerName;
                if (move_uploaded_file($_FILES['banner']['tmp_name'], $dest)) {
                    $bannerPath = 'uploads/' . $bannerName;
                } else {
                    $error = 'Failed to upload banner. Check folder permissions.';
                }
            }
        }

        if (!$error) {
            $uid  = (int)$_SESSION['user_id'];
            $stmt = $conn->prepare(
                "INSERT INTO channels (user_id, name, description, banner) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param('isss', $uid, $name, $desc, $bannerPath);
            if ($stmt->execute()) {
                header('Location: /cookstream/channel/dashboard.php?created=1');
                exit;
            } else {
                $error = 'Database error: ' . $conn->error;
            }
        }
    }
}

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Create Channel – CookStream</title>
  <meta name="description" content="Start your cooking channel on CookStream and share your recipes with the world.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/cookstream/assets/css/style.css">
  <style>
    /* ── Page Shell ── */
    body { font-family: 'Outfit', sans-serif; }

    .create-page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
      background: radial-gradient(ellipse at 20% 50%, hsla(25,100%,60%,0.15) 0%, transparent 60%),
                  radial-gradient(ellipse at 80% 20%, hsla(260,80%,60%,0.12) 0%, transparent 60%),
                  var(--bg-dark, #0f0f0f);
    }

    .create-card {
      width: 100%;
      max-width: 560px;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.09);
      border-radius: 24px;
      padding: 48px 44px;
      backdrop-filter: blur(20px);
      box-shadow: 0 32px 80px rgba(0,0,0,0.5);
      animation: slideUp 0.5s cubic-bezier(.23,1,.32,1) both;
    }

    @keyframes slideUp {
      from { opacity:0; transform:translateY(30px); }
      to   { opacity:1; transform:translateY(0); }
    }

    .create-icon {
      font-size: 3rem;
      display: block;
      text-align: center;
      margin-bottom: 8px;
    }

    .create-card h1 {
      font-size: 1.9rem;
      font-weight: 800;
      color: #fff;
      text-align: center;
      margin: 0 0 6px;
    }

    .create-card .subtitle {
      text-align: center;
      color: rgba(255,255,255,0.45);
      font-size: 0.9rem;
      margin-bottom: 36px;
    }

    /* ── Alert ── */
    .alert {
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 0.88rem;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .alert-error   { background: rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.35); color:#fca5a5; }
    .alert-success { background: rgba(34,197,94,0.15);  border:1px solid rgba(34,197,94,0.35);  color:#86efac; }

    /* ── Form ── */
    .form-group { margin-bottom: 22px; }

    .form-group label {
      display: block;
      font-size: 0.82rem;
      font-weight: 600;
      color: rgba(255,255,255,0.6);
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin-bottom: 8px;
    }

    .form-group input[type="text"],
    .form-group textarea {
      width: 100%;
      padding: 13px 16px;
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 12px;
      color: #fff;
      font-family: 'Outfit', sans-serif;
      font-size: 0.97rem;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
      box-sizing: border-box;
    }

    .form-group input[type="text"]:focus,
    .form-group textarea:focus {
      border-color: hsl(25,100%,60%);
      box-shadow: 0 0 0 3px hsla(25,100%,60%,0.18);
    }

    .form-group textarea {
      resize: vertical;
      min-height: 100px;
    }

    /* ── Banner Upload ── */
    .banner-upload {
      border: 2px dashed rgba(255,255,255,0.15);
      border-radius: 14px;
      padding: 28px;
      text-align: center;
      cursor: pointer;
      transition: border-color 0.2s, background 0.2s;
      position: relative;
    }
    .banner-upload:hover {
      border-color: hsl(25,100%,60%);
      background: rgba(255,140,0,0.05);
    }
    .banner-upload input[type="file"] {
      position: absolute;
      inset: 0;
      opacity: 0;
      cursor: pointer;
      width: 100%;
      height: 100%;
    }
    .banner-upload .upload-icon { font-size: 2rem; margin-bottom: 8px; }
    .banner-upload p { color: rgba(255,255,255,0.4); font-size: 0.85rem; margin: 0; }
    .banner-upload span { color: hsl(25,100%,60%); font-weight: 600; }

    #banner-preview {
      width: 100%;
      border-radius: 10px;
      margin-top: 12px;
      max-height: 140px;
      object-fit: cover;
      display: none;
    }

    /* ── Submit Button ── */
    .btn-create {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, hsl(25,100%,55%), hsl(10,90%,55%));
      color: #fff;
      border: none;
      border-radius: 12px;
      font-family: 'Outfit', sans-serif;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      letter-spacing: 0.03em;
      transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
      margin-top: 8px;
      box-shadow: 0 8px 24px hsla(25,100%,50%,0.35);
    }
    .btn-create:hover  { transform: translateY(-2px); box-shadow: 0 12px 32px hsla(25,100%,50%,0.45); }
    .btn-create:active { transform: translateY(0); opacity: 0.9; }

    .back-link {
      display: block;
      text-align: center;
      margin-top: 20px;
      color: rgba(255,255,255,0.35);
      font-size: 0.87rem;
      text-decoration: none;
      transition: color 0.2s;
    }
    .back-link:hover { color: rgba(255,255,255,0.7); }

    /* ── Char counter ── */
    .char-counter {
      text-align: right;
      font-size: 0.75rem;
      color: rgba(255,255,255,0.28);
      margin-top: 4px;
    }
  </style>
</head>
<body>

<!-- ── Navbar ── -->
<nav class="navbar">
  <a class="navbar-brand" href="/cookstream/">
    <span class="logo-icon">🍳</span> CookStream
  </a>
  <div class="nav-actions">
    <a href="/cookstream/auth/logout.php" class="btn btn-ghost">Sign Out</a>
    <div class="avatar" title="<?= sanitize($user['name']) ?>"><?= strtoupper($user['name'][0]) ?></div>
  </div>
</nav>

<!-- ── Main ── -->
<div class="create-page">
  <div class="create-card">
    <span class="create-icon">📺</span>
    <h1>Create Your Channel</h1>
    <p class="subtitle">Share your culinary passion with the world</p>

    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?= sanitize($success) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="create-channel-form">

      <div class="form-group">
        <label for="channel-name">Channel Name *</label>
        <input type="text" id="channel-name" name="name" maxlength="150"
               placeholder="e.g. Spice Kitchen by Priya"
               value="<?= sanitize($_POST['name'] ?? '') ?>" required>
        <div class="char-counter"><span id="name-count">0</span> / 150</div>
      </div>

      <div class="form-group">
        <label for="channel-desc">Description</label>
        <textarea id="channel-desc" name="description" maxlength="500"
                  placeholder="Tell viewers what kind of recipes you'll share…"><?= sanitize($_POST['description'] ?? '') ?></textarea>
        <div class="char-counter"><span id="desc-count">0</span> / 500</div>
      </div>

      <div class="form-group">
        <label>Channel Banner (optional)</label>
        <div class="banner-upload" id="banner-drop">
          <input type="file" name="banner" id="banner-input" accept="image/*">
          <div class="upload-icon">🖼️</div>
          <p><span>Click to upload</span> or drag & drop</p>
          <p>JPG, PNG, WEBP · Max 5 MB</p>
          <img id="banner-preview" src="" alt="Banner preview">
        </div>
      </div>

      <button type="submit" class="btn-create" id="submit-btn">
        🚀 Launch My Channel
      </button>
    </form>

    <a href="/cookstream/" class="back-link">← Back to Home</a>
  </div>
</div>

<script>
  // Char counters
  const nameInput = document.getElementById('channel-name');
  const descInput = document.getElementById('channel-desc');
  const nameCount = document.getElementById('name-count');
  const descCount = document.getElementById('desc-count');

  function updateCount(el, counter) {
    counter.textContent = el.value.length;
  }
  nameInput.addEventListener('input', () => updateCount(nameInput, nameCount));
  descInput.addEventListener('input', () => updateCount(descInput, descCount));
  updateCount(nameInput, nameCount);
  updateCount(descInput, descCount);

  // Banner preview
  document.getElementById('banner-input').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.getElementById('banner-preview');
      img.src = e.target.result;
      img.style.display = 'block';
    };
    reader.readAsDataURL(file);
  });

  // Submit loading state
  document.getElementById('create-channel-form').addEventListener('submit', function () {
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.textContent = '⏳ Creating…';
  });
</script>
</body>
</html>
