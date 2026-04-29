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
        <a href="/cookstream/shorts/upload.php" class="btn btn-outline" id="shorts-upload-btn" style="background:linear-gradient(135deg,rgba(168,85,247,.2),rgba(236,72,153,.2));border-color:rgba(168,85,247,.5);color:#d8b4fe">📱 Short</a>
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

<!-- ── Filter Bar ──────────────────────────────────────────────────────── -->
<div class="filter-bar">
  <button class="filter-btn <?= !$cat && !$q ? 'active':'' ?>" data-filter="all">🍽 All</button>
  <button class="filter-btn <?= $cat==='veg'   ?'active':'' ?>" data-filter="veg">🥦 Veg</button>
  <button class="filter-btn <?= $cat==='non-veg'?'active':'' ?>" data-filter="non-veg">🍗 Non-Veg</button>
  <button class="filter-btn <?= $cat==='trending'?'active':'' ?>" data-filter="trending">🔥 Trending</button>
  <a href="/cookstream/shorts/view.php" class="filter-btn" style="background:linear-gradient(135deg,rgba(168,85,247,.25),rgba(236,72,153,.25));border-color:rgba(168,85,247,.4);color:#d8b4fe;text-decoration:none">📱 Shorts</a>
</div>

<?php if (!empty($recentShorts)): ?>
<!-- ── Shorts Strip ──────────────────────────────────────────────────────── -->
<div class="container" style="margin-bottom:0;padding-bottom:0">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
    <h2 class="section-title" style="margin:0">
      <span style="background:linear-gradient(135deg,#a855f7,#ec4899);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">📱 Shorts</span>
    </h2>
    <a href="/cookstream/shorts/view.php" style="font-size:.82rem;color:#a855f7;font-weight:600;text-decoration:none">View All →</a>
  </div>
  <div style="display:flex;gap:12px;overflow-x:auto;padding-bottom:14px;scrollbar-width:none">
    <?php foreach ($recentShorts as $s): ?>
    <a href="/cookstream/shorts/view.php?id=<?= $s['id'] ?>" style="
      flex-shrink:0;width:130px;text-decoration:none;
      border-radius:14px;overflow:hidden;position:relative;
      background:#111;border:1px solid rgba(255,255,255,.08);
      display:block;
    ">
      <!-- 9:16 thumbnail or video preview -->
      <div style="aspect-ratio:9/16;background:#111;overflow:hidden;display:flex;align-items:center;justify-content:center">
        <?php if ($s['thumbnail_path']): ?>
          <img src="/cookstream/<?= sanitize($s['thumbnail_path']) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
        <?php else: ?>
          <span style="font-size:2rem"><?= $s['category']==='veg'?'🥦':'🍗' ?></span>
        <?php endif; ?>
      </div>
      <!-- Overlay -->
      <div style="position:absolute;bottom:0;left:0;right:0;padding:8px;
        background:linear-gradient(to top,rgba(0,0,0,.8) 0%,transparent 100%)">
        <div style="font-size:.72rem;font-weight:700;color:#fff;line-height:1.2"><?= sanitize(mb_substr($s['title'],0,30)) ?></div>
        <div style="font-size:.65rem;color:rgba(255,255,255,.5);margin-top:2px">❤️ <?= formatViews((int)$s['like_count']) ?></div>
      </div>
      <!-- Play icon -->
      <div style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,.5);border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:.75rem">▶</div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

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
