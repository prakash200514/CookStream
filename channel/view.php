<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /cookstream/'); exit; }

$user = getCurrentUser();

// Fetch channel info
$stmt = $conn->prepare("SELECT * FROM channels WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$ch = $stmt->get_result()->fetch_assoc();
if (!$ch) { header('Location: /cookstream/'); exit; }

// Fetch channel videos
$vStmt = $conn->prepare("SELECT * FROM videos WHERE channel_id = ? ORDER BY created_at DESC");
$vStmt->bind_param('i', $id);
$vStmt->execute();
$videos = $vStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Sub info
$scStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM subscriptions WHERE channel_id = ?");
$scStmt->bind_param('i', $id);
$scStmt->execute();
$subCount = (int)$scStmt->get_result()->fetch_assoc()['cnt'];

$userSubbed = false;
if ($user) {
    $usStmt = $conn->prepare("SELECT id FROM subscriptions WHERE channel_id = ? AND user_id = ?");
    $usStmt->bind_param('ii', $id, $user['id']);
    $usStmt->execute();
    $userSubbed = $usStmt->get_result()->num_rows > 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= sanitize($ch['name']) ?> – CookStream</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/cookstream/assets/css/style.css">
<style>
  .channel-header-big {
    padding: 40px 0;
    border-bottom: 1px solid var(--border);
    margin-bottom: 24px;
    display: flex;
    gap: 32px;
    align-items: center;
  }
  .ch-avatar-lg {
    width: 120px; height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), #ff9a5c);
    display: flex; align-items: center; justify-content: center;
    font-size: 48px; font-weight: 800; color: #fff;
  }
  .ch-meta-lg h1 { font-size: 32px; font-weight: 800; margin-bottom: 4px; }
  .ch-meta-lg p { color: var(--muted); font-size: 14px; }
  .ch-actions { margin-top: 16px; }
  
  /* Override sub btn for channel page */
  .btn-sub {
    background: #fff; color: #000;
    padding: 10px 24px; border-radius: 50px;
    font-weight: 700; border: none; cursor: pointer;
  }
  .btn-sub.subbed { background: var(--bg3); color: #fff; }
</style>
</head>
<body>

<nav class="navbar">
  <div style="display:flex;align-items:center;gap:16px;">
    <a class="navbar-brand" href="/cookstream/">
      <img src="/cookstream/assets/img/logo.png" alt="CookStream Logo">
    </a>
  </div>
  <div class="search-wrap">
    <div class="search-box"><input type="text" placeholder="Search"></div>
  </div>
  <div class="nav-actions">
    <?php if ($user): ?>
      <div class="avatar"><?= strtoupper($user['name'][0]) ?></div>
    <?php else: ?>
      <a href="/cookstream/auth/login.php" class="btn btn-outline btn-sm">Sign in</a>
    <?php endif; ?>
  </div>
</nav>

<div class="main-wrapper">
  <main class="main-content" style="margin-left:0; padding: 24px 80px;">
    <div class="channel-header-big">
      <div class="ch-avatar-lg"><?= strtoupper($ch['name'][0]) ?></div>
      <div class="ch-meta-lg">
        <h1><?= sanitize($ch['name']) ?></h1>
        <p><?= sanitize($ch['description']) ?></p>
        <p style="margin-top:8px;"><strong><?= formatViews($subCount) ?></strong> subscribers • <strong><?= count($videos) ?></strong> videos</p>
        <div class="ch-actions">
           <?php if (!$user): ?>
             <button class="btn-sub" onclick="location.href='/cookstream/auth/login.php'">Subscribe</button>
           <?php elseif ($user['id'] !== $ch['user_id']): ?>
             <button id="sub-btn" class="btn-sub <?= $userSubbed ? 'subbed' : '' ?>" onclick="toggleSub(<?= $id ?>)">
               <?= $userSubbed ? 'Subscribed' : 'Subscribe' ?>
             </button>
           <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="video-grid">
      <?php foreach ($videos as $v): ?>
        <a class="video-card" href="/cookstream/video/watch.php?id=<?= $v['id'] ?>">
          <div class="video-thumb">
            <img src="/cookstream/<?= sanitize($v['thumbnail_path']) ?>" alt="">
          </div>
          <div class="video-info">
            <div class="v-details">
              <h3 style="margin-left:0;"><?= sanitize($v['title']) ?></h3>
              <div class="video-meta">
                <span><?= formatViews((int)$v['views']) ?> views</span>
                <span><?= timeAgo($v['created_at']) ?></span>
              </div>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </main>
</div>

<script>
async function toggleSub(channelId) {
  const btn = document.getElementById('sub-btn');
  const res = await fetch('/cookstream/api/subscribe.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'channel_id=' + channelId
  });
  const data = await res.json();
  if (data.subscribed !== undefined) {
    btn.textContent = data.subscribed ? 'Subscribed' : 'Subscribe';
    btn.classList.toggle('subbed', data.subscribed);
  }
}
</script>
</body>
</html>
