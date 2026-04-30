<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$user    = getCurrentUser();
$channel = getUserChannel($conn);

// Search & filter
$q   = trim($_GET['q']   ?? '');
$cat = trim($_GET['cat'] ?? '');

$where  = "WHERE 1=1";
$params = [];
$types  = '';

if ($q) {
    $where   .= " AND v.title LIKE ?";
    $params[] = "%$q%";
    $types   .= 's';
}
if (in_array($cat, ['veg','non-veg'])) {
    $where   .= " AND v.category = ?";
    $params[] = $cat;
    $types   .= 's';
}

// Trending: most viewed in last 30 days
$trending = ($cat === 'trending');
$orderBy  = $trending ? "ORDER BY v.views DESC" : "ORDER BY v.created_at DESC";

if ($trending) { $where = "WHERE 1=1" . ($q ? " AND v.title LIKE ?" : ''); }

$sql  = "SELECT v.*, c.name AS channel_name
         FROM videos v
         JOIN channels c ON c.id = v.channel_id
         $where
         $orderBy
         LIMIT 60";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$videos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Recent shorts for homepage strip
$recentShorts = getAllShorts($conn, 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CookStream – Discover Food Videos</title>
<meta name="description" content="Watch, upload and share food videos.">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/cookstream/assets/css/style.css">
</head>
<body>

<!-- ── Navbar ────────────────────────────────────────────────────────── -->
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
      <input id="search-input" type="text" placeholder="Search" value="<?= sanitize($q) ?>">
    </div>
    <button class="search-btn"><i class="fas fa-search"></i></button>
    <div class="mic-btn"><i class="fas fa-microphone"></i></div>
    <div id="search-results" class="search-results"></div>
  </div>

  <div class="nav-actions">
    <?php if ($user): ?>
      <a href="/cookstream/video/upload.php" class="btn-create">
        <i class="fas fa-plus"></i>
        <span>Create</span>
      </a>
      
      <div class="notification-wrap">
        <i class="far fa-bell"></i>
        <div class="notification-badge">9+</div>
      </div>
      
      <div class="user-menu-wrap">
        <div class="avatar" id="avatar-toggle"><?= strtoupper($user['name'][0]) ?></div>
        <div class="user-dropdown" id="user-dropdown">
          <div class="dd-header">
            <div class="dd-name"><?= sanitize($user['name']) ?></div>
            <div class="dd-role"><?= sanitize($user['email']) ?></div>
          </div>
          <a href="/cookstream/channel/dashboard.php"><i class="fas fa-user-circle dd-icon"></i> Your channel</a>
          <a href="/cookstream/auth/logout.php" class="dd-danger"><i class="fas fa-sign-out-alt dd-icon"></i> Sign out</a>
        </div>
      </div>
    <?php else: ?>
      <a href="/cookstream/video/upload.php" class="btn-create">
        <i class="fas fa-plus"></i>
        <span>Create</span>
      </a>
      <div class="notification-wrap">
        <i class="far fa-bell"></i>
      </div>
      <a href="/cookstream/auth/login.php" class="btn btn-outline btn-sm" style="border-radius:20px;border-color:#333;color:#3ea6ff;">
        <i class="far fa-user-circle" style="margin-right:8px;"></i> Sign in
      </a>
    <?php endif; ?>
  </div>
</nav>

<div class="main-wrapper">
  <!-- ── Sidebar ───────────────────────────────────────────────────────── -->
  <aside class="sidebar">
    <a href="/cookstream/" class="sidebar-item active"><i class="fas fa-home"></i> Home</a>
    <a href="/cookstream/shorts/view.php" class="sidebar-item"><i class="fas fa-bolt"></i> Shorts</a>
    <a href="#" class="sidebar-item"><i class="fas fa-layer-group"></i> Subscriptions</a>
    
    <div class="sidebar-section">
      <a href="#" class="sidebar-item">You <i class="fas fa-chevron-right" style="font-size:10px;margin-left:auto;"></i></a>
      <a href="#" class="sidebar-item"><i class="fas fa-history"></i> History</a>
      <a href="#" class="sidebar-item"><i class="fas fa-play-circle"></i> Your videos</a>
      <a href="#" class="sidebar-item"><i class="fas fa-clock"></i> Watch later</a>
      <a href="#" class="sidebar-item"><i class="fas fa-thumbs-up"></i> Liked videos</a>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-title">Subscriptions</div>
      <!-- Placeholder subs -->
      <a href="#" class="sidebar-item"><div class="v-avatar" style="width:24px;height:24px;font-size:10px;">C</div> Chef John</a>
      <a href="#" class="sidebar-item"><div class="v-avatar" style="width:24px;height:24px;font-size:10px;">G</div> Gordon R.</a>
    </div>
  </aside>

  <!-- ── Main Content ─────────────────────────────────────────────────── -->
  <main class="main-content">
    
    <!-- Category Chips -->
    <div class="filter-bar">
      <button class="filter-btn <?= !$cat && !$q ? 'active':'' ?>" data-filter="all">All</button>
      <button class="filter-btn <?= $cat==='veg' ? 'active':'' ?>" data-filter="veg">Veg</button>
      <button class="filter-btn <?= $cat==='non-veg' ? 'active':'' ?>" data-filter="non-veg">Non-Veg</button>
      <button class="filter-btn <?= $cat==='trending' ? 'active':'' ?>" data-filter="trending">Trending</button>
      <button class="filter-btn">Mixes</button>
      <button class="filter-btn">Cooking</button>
      <button class="filter-btn">Street Food</button>
      <button class="filter-btn">Live</button>
    </div>

    <div class="container">
      <?php if (empty($videos)): ?>
        <div class="empty-state">
          <i class="fas fa-search icon" style="color:#333;"></i>
          <h3>No results found</h3>
          <p>Try different keywords or filters.</p>
        </div>
      <?php else: ?>
        <div class="video-grid">
          <?php foreach ($videos as $v): ?>
            <a class="video-card" href="/cookstream/video/watch.php?id=<?= $v['id'] ?>">
              <div class="video-thumb">
                <?php if ($v['thumbnail_path']): ?>
                  <img src="/cookstream/<?= sanitize($v['thumbnail_path']) ?>" alt="<?= sanitize($v['title']) ?>" loading="lazy">
                <?php else: ?>
                  <div class="thumb-placeholder"><i class="fas fa-utensils" style="font-size:32px;color:#333;"></i></div>
                <?php endif; ?>
              </div>
              <div class="video-info">
                <div class="v-avatar"><?= strtoupper($v['channel_name'][0]) ?></div>
                <div class="v-details">
                  <h3><?= sanitize($v['title']) ?></h3>
                  <span class="channel-name"><?= sanitize($v['channel_name']) ?> <i class="fas fa-check-circle" style="font-size:10px;"></i></span>
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
