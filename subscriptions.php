<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: /cookstream/auth/login.php');
    exit;
}

// Fetch videos from subscribed channels
$sql = "SELECT v.*, c.name AS channel_name
        FROM videos v
        JOIN channels c ON c.id = v.channel_id
        JOIN subscriptions s ON s.channel_id = v.channel_id
        WHERE s.user_id = ?
        ORDER BY v.created_at DESC
        LIMIT 60";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$videos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch user subscriptions for sidebar
$mySubs = [];
$subStmt = $conn->prepare(
    "SELECT c.id, c.name FROM subscriptions s 
     JOIN channels c ON c.id = s.channel_id 
     WHERE s.user_id = ? 
     ORDER BY c.name ASC"
);
$subStmt->bind_param('i', $user['id']);
$subStmt->execute();
$mySubs = $subStmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Subscriptions – CookStream</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/cookstream/assets/css/style.css">
</head>
<body>

<!-- ── Navbar (Partial duplication for simplicity, usually included) ── -->
<nav class="navbar">
  <div style="display:flex;align-items:center;gap:16px;">
    <div class="nav-hamburger" id="nav-hamburger">
      <i class="fas fa-bars"></i>
    </div>
    <a class="navbar-brand" href="/cookstream/">
      <img src="/cookstream/assets/img/logo.png" alt="CookStream Logo">
    </a>
  </div>

  <div class="search-wrap">
    <div class="search-box">
      <input id="search-input" type="text" placeholder="Search">
    </div>
    <button class="search-btn"><i class="fas fa-search"></i></button>
  </div>

  <div class="nav-actions">
    <a href="/cookstream/video/upload.php" class="btn-create">
      <i class="fas fa-plus"></i>
      <span>Create</span>
    </a>
    <div class="user-menu-wrap">
      <div class="avatar" id="avatar-toggle"><?= strtoupper($user['name'][0]) ?></div>
    </div>
  </div>
</nav>

<div class="main-wrapper">
  <!-- ── Sidebar ── -->
  <aside class="sidebar">
    <a href="/cookstream/" class="sidebar-item"><i class="fas fa-home"></i> Home</a>
    <a href="/cookstream/shorts/view.php" class="sidebar-item"><i class="fas fa-bolt"></i> Shorts</a>
    <a href="/cookstream/subscriptions.php" class="sidebar-item active"><i class="fas fa-layer-group"></i> Subscriptions</a>
    
    <div class="sidebar-section">
      <a href="/cookstream/channel/dashboard.php" class="sidebar-item"><i class="fas fa-play-circle"></i> Your videos</a>
      <a href="#" class="sidebar-item"><i class="fas fa-history"></i> History</a>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-title">Subscriptions</div>
      <?php foreach ($mySubs as $sub): ?>
        <a href="/cookstream/channel/view.php?id=<?= $sub['id'] ?>" class="sidebar-item">
          <div class="v-avatar" style="width:24px;height:24px;font-size:10px;"><?= strtoupper($sub['name'][0]) ?></div>
          <span><?= sanitize($sub['name']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </aside>

  <!-- ── Main Content ── -->
  <main class="main-content">
    <div class="container">
      <h2 style="margin-bottom:24px;">Latest from your subscriptions</h2>
      
      <?php if (empty($videos)): ?>
        <div class="empty-state">
          <i class="fas fa-layer-group icon"></i>
          <h3>Don't miss a video</h3>
          <p>When you subscribe to channels, their latest videos will show up here.</p>
          <a href="/cookstream/" class="btn btn-primary" style="margin-top:20px;">Explore channels</a>
        </div>
      <?php else: ?>
        <div class="video-grid">
          <?php foreach ($videos as $v): ?>
            <a class="video-card" href="/cookstream/video/watch.php?id=<?= $v['id'] ?>">
              <div class="video-thumb">
                <img src="/cookstream/<?= sanitize($v['thumbnail_path']) ?>" alt="" loading="lazy">
              </div>
              <div class="video-info">
                <div class="v-avatar"><?= strtoupper($v['channel_name'][0]) ?></div>
                <div class="v-details">
                  <h3><?= sanitize($v['title']) ?></h3>
                  <span class="channel-name"><?= sanitize($v['channel_name']) ?></span>
                  <div class="video-meta">
                    <span><?= formatViews((int)$v['views']) ?> views</span>
                    <span><?= timeAgo($v['created_at']) ?></span>
                  </div>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<script src="/cookstream/assets/js/main.js"></script>
</body>
</html>
