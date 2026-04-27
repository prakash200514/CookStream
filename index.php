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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CookStream – Discover Food Videos</title>
<meta name="description" content="Watch, upload and share step-by-step food-making videos. Find veg and non-veg recipes from passionate home chefs.">
<link rel="stylesheet" href="/cookstream/assets/css/style.css">
</head>
<body>

<!-- ── Navbar ────────────────────────────────────────────────────────── -->
<nav class="navbar">
  <a class="navbar-brand" href="/cookstream/">
    <span class="logo-icon">🍳</span> CookStream
  </a>

  <div class="search-wrap">
    <span class="search-icon">🔍</span>
    <input id="search-input" type="text" placeholder="Search food videos…"
           value="<?= sanitize($q) ?>">
    <div id="search-results" class="search-results"></div>
  </div>

  <div class="nav-actions">
    <?php if ($user): ?>
      <?php if ($channel): ?>
        <a href="/cookstream/video/upload.php" class="btn btn-primary" id="upload-btn">⬆ Upload</a>
        <a href="/cookstream/channel/dashboard.php" class="btn btn-outline" id="channel-btn">📺 My Channel</a>
      <?php else: ?>
        <a href="/cookstream/channel/create.php" class="btn btn-outline" id="create-channel-btn">＋ Create Channel</a>
      <?php endif; ?>
      <a href="/cookstream/auth/logout.php" class="btn btn-ghost">Sign Out</a>
      <div class="avatar" title="<?= sanitize($user['name']) ?>"><?= strtoupper($user['name'][0]) ?></div>
    <?php else: ?>
      <a href="/cookstream/auth/login.php" class="btn btn-outline" id="login-btn">Sign In</a>
      <a href="/cookstream/auth/register.php" class="btn btn-primary" id="register-btn">Join Free</a>
    <?php endif; ?>
  </div>
</nav>

<!-- ── Filter Bar ─────────────────────────────────────────────────────── -->
<div class="filter-bar">
  <button class="filter-btn <?= !$cat && !$q ? 'active':'' ?>" data-filter="all">🍽 All</button>
  <button class="filter-btn <?= $cat==='veg'   ?'active':'' ?>" data-filter="veg">🥦 Veg</button>
  <button class="filter-btn <?= $cat==='non-veg'?'active':'' ?>" data-filter="non-veg">🍗 Non-Veg</button>
  <button class="filter-btn <?= $cat==='trending'?'active':'' ?>" data-filter="trending">🔥 Trending</button>
</div>

<!-- ── Video Grid ─────────────────────────────────────────────────────── -->
<div class="container">
  <h2 class="section-title">
    <?php if ($q): ?>Results for "<?= sanitize($q) ?>"
    <?php elseif ($cat === 'trending'): ?>🔥 Trending Now
    <?php elseif ($cat === 'veg'): ?>🥦 Vegetarian Recipes
    <?php elseif ($cat === 'non-veg'): ?>🍗 Non-Vegetarian Recipes
    <?php else: ?>Latest Videos<?php endif; ?>
  </h2>

  <?php if (empty($videos)): ?>
    <div class="empty-state">
      <span class="icon">🎬</span>
      <h3>No videos found</h3>
      <p><?= $q ? "Try a different search term." : "Be the first to upload a food video!" ?></p>
      <?php if ($user && $channel): ?>
        <a href="/cookstream/video/upload.php" class="btn btn-primary" style="margin-top:20px">Upload Video</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="video-grid">
      <?php foreach ($videos as $v): ?>
        <a class="video-card" href="/cookstream/video/watch.php?id=<?= $v['id'] ?>">
          <div class="video-thumb">
            <?php if ($v['thumbnail_path']): ?>
              <img src="/cookstream/<?= sanitize($v['thumbnail_path']) ?>" alt="<?= sanitize($v['title']) ?>" loading="lazy">
            <?php else: ?>
              <div class="thumb-placeholder">🍽</div>
            <?php endif; ?>
            <div class="play-overlay"><span>▶</span></div>
          </div>
          <div class="video-info">
            <h3><?= sanitize($v['title']) ?></h3>
            <div class="video-meta">
              <span class="channel-name">📺 <?= sanitize($v['channel_name']) ?></span>
              <?= vegBadge($v['category']) ?>
            </div>
            <div class="video-meta" style="margin-top:6px">
              <span>👁 <?= formatViews((int)$v['views']) ?> views</span>
              <span><?= timeAgo($v['created_at']) ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script src="/cookstream/assets/js/main.js"></script>
</body>
</html>
