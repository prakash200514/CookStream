<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$channel = getUserChannel($conn);
if (!$channel) {
    header('Location: /cookstream/channel/create.php');
    exit;
}

$created = isset($_GET['created']);

// Fetch channel's videos
$stmt = $conn->prepare(
    "SELECT v.*, 
            (SELECT COUNT(*) FROM likes    WHERE video_id = v.id) AS like_count,
            (SELECT COUNT(*) FROM comments WHERE video_id = v.id) AS comment_count
     FROM videos v
     WHERE v.channel_id = ?
     ORDER BY v.created_at DESC"
);
$stmt->bind_param('i', $channel['id']);
$stmt->execute();
$videos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Aggregate stats
$totalViews = array_sum(array_column($videos, 'views'));
$totalLikes = array_sum(array_column($videos, 'like_count'));

// Subscriber count
$subStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM subscriptions WHERE channel_id = ?");
$subStmt->bind_param('i', $channel['id']);
$subStmt->execute();
$subscribers = (int)$subStmt->get_result()->fetch_assoc()['cnt'];

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= sanitize($channel['name']) ?> – Channel Dashboard | CookStream</title>
  <meta name="description" content="Manage your CookStream channel, view stats and upload new food videos.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/cookstream/assets/css/style.css">
  <style>
    body { font-family: 'Outfit', sans-serif; }

    /* ── Banner ── */
    .channel-banner {
      width: 100%;
      height: 200px;
      object-fit: cover;
      display: block;
    }
    .banner-placeholder {
      height: 200px;
      background: linear-gradient(135deg, hsl(25,100%,40%), hsl(260,80%,40%), hsl(200,80%,35%));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 4rem;
    }

    /* ── Channel Header ── */
    .channel-header {
      max-width: 1100px;
      margin: 0 auto;
      padding: 28px 24px 0;
      display: flex;
      align-items: flex-start;
      gap: 24px;
      flex-wrap: wrap;
    }

    .channel-avatar {
      width: 90px;
      height: 90px;
      border-radius: 50%;
      background: linear-gradient(135deg, hsl(25,100%,55%), hsl(10,90%,55%));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.5rem;
      font-weight: 800;
      color: #fff;
      flex-shrink: 0;
      margin-top: -45px;
      border: 4px solid var(--bg-dark, #0f0f0f);
      box-shadow: 0 8px 24px rgba(0,0,0,0.4);
    }

    .channel-info { flex: 1; min-width: 200px; }
    .channel-info h1 { font-size: 1.7rem; font-weight: 800; color: #fff; margin: 0 0 4px; }
    .channel-info p  { color: rgba(255,255,255,0.45); font-size: 0.88rem; margin: 0 0 12px; }

    .channel-actions { display: flex; gap: 10px; flex-wrap: wrap; }

    /* ── Stats Bar ── */
    .stats-bar {
      max-width: 1100px;
      margin: 24px auto;
      padding: 0 24px;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 16px;
    }

    .stat-card {
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 16px;
      padding: 20px 22px;
      text-align: center;
      transition: transform 0.2s;
    }
    .stat-card:hover { transform: translateY(-3px); }
    .stat-icon  { font-size: 1.6rem; margin-bottom: 6px; }
    .stat-value { font-size: 1.8rem; font-weight: 800; color: #fff; }
    .stat-label { font-size: 0.8rem; color: rgba(255,255,255,0.4); margin-top: 2px; }

    /* ── Section ── */
    .dashboard-section {
      max-width: 1100px;
      margin: 0 auto 60px;
      padding: 0 24px;
    }

    .section-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 12px;
    }

    .section-header h2 { font-size: 1.25rem; font-weight: 700; color: #fff; margin: 0; }

    /* ── Video Table ── */
    .video-table {
      width: 100%;
      border-collapse: collapse;
    }

    .video-table th, .video-table td {
      padding: 14px 16px;
      text-align: left;
      font-size: 0.88rem;
    }

    .video-table th {
      color: rgba(255,255,255,0.4);
      text-transform: uppercase;
      letter-spacing: 0.06em;
      font-size: 0.75rem;
      border-bottom: 1px solid rgba(255,255,255,0.07);
    }

    .video-table td {
      color: rgba(255,255,255,0.75);
      border-bottom: 1px solid rgba(255,255,255,0.05);
    }

    .video-table tr:hover td { background: rgba(255,255,255,0.03); }

    .video-thumb-sm {
      width: 72px;
      height: 44px;
      border-radius: 6px;
      object-fit: cover;
      background: rgba(255,255,255,0.07);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      overflow: hidden;
    }
    .video-thumb-sm img { width: 100%; height: 100%; object-fit: cover; }

    .video-title-cell { display: flex; align-items: center; gap: 12px; }

    .badge-cat {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 20px;
      font-size: 0.72rem;
      font-weight: 600;
    }
    .badge-veg     { background: rgba(34,197,94,0.15); color:#86efac; border:1px solid rgba(34,197,94,0.3); }
    .badge-non-veg { background: rgba(239,68,68,0.15); color:#fca5a5; border:1px solid rgba(239,68,68,0.3); }

    .action-link {
      color: rgba(255,255,255,0.4);
      text-decoration: none;
      font-size: 0.82rem;
      transition: color 0.2s;
      margin-right: 10px;
    }
    .action-link:hover { color: hsl(25,100%,60%); }

    /* ── Empty State ── */
    .empty-dash {
      text-align: center;
      padding: 60px 20px;
      color: rgba(255,255,255,0.3);
      background: rgba(255,255,255,0.03);
      border: 1px dashed rgba(255,255,255,0.1);
      border-radius: 16px;
    }
    .empty-dash .big-icon { font-size: 3rem; margin-bottom: 12px; }
    .empty-dash h3 { color: rgba(255,255,255,0.5); margin: 0 0 8px; }
    .empty-dash p  { margin: 0 0 20px; font-size: 0.88rem; }

    /* ── Toast ── */
    .toast {
      position: fixed;
      bottom: 30px;
      right: 30px;
      background: rgba(34,197,94,0.9);
      color: #fff;
      padding: 14px 20px;
      border-radius: 12px;
      font-weight: 600;
      font-size: 0.9rem;
      box-shadow: 0 8px 24px rgba(0,0,0,0.4);
      animation: toastIn 0.4s ease both;
      z-index: 9999;
    }
    @keyframes toastIn {
      from { opacity:0; transform:translateY(20px); }
      to   { opacity:1; transform:translateY(0); }
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
    <a href="/cookstream/video/upload.php" class="btn btn-primary" id="upload-video-btn">⬆ Upload Video</a>
    <a href="/cookstream/auth/logout.php" class="btn btn-ghost">Sign Out</a>
    <div class="avatar" title="<?= sanitize($user['name']) ?>"><?= strtoupper($user['name'][0]) ?></div>
  </div>
</nav>

<!-- ── Channel Banner ── -->
<?php if ($channel['banner']): ?>
  <img class="channel-banner" src="/cookstream/<?= sanitize($channel['banner']) ?>"
       alt="<?= sanitize($channel['name']) ?> banner">
<?php else: ?>
  <div class="banner-placeholder">🍳</div>
<?php endif; ?>

<!-- ── Channel Header ── -->
<div class="channel-header">
  <div class="channel-avatar"><?= strtoupper($channel['name'][0]) ?></div>
  <div class="channel-info">
    <h1><?= sanitize($channel['name']) ?></h1>
    <?php if ($channel['description']): ?>
      <p><?= sanitize($channel['description']) ?></p>
    <?php endif; ?>
    <div class="channel-actions">
      <a href="/cookstream/video/upload.php" class="btn btn-primary">⬆ Upload Video</a>
    </div>
  </div>
</div>

<!-- ── Stats ── -->
<div class="stats-bar">
  <div class="stat-card">
    <div class="stat-icon">🎬</div>
    <div class="stat-value"><?= count($videos) ?></div>
    <div class="stat-label">Videos</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">👁</div>
    <div class="stat-value"><?= formatViews($totalViews) ?></div>
    <div class="stat-label">Total Views</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">❤️</div>
    <div class="stat-value"><?= formatViews($totalLikes) ?></div>
    <div class="stat-label">Total Likes</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🔔</div>
    <div class="stat-value"><?= formatViews($subscribers) ?></div>
    <div class="stat-label">Subscribers</div>
  </div>
</div>

<!-- ── Videos List ── -->
<div class="dashboard-section">
  <div class="section-header">
    <h2>📹 Your Videos</h2>
    <a href="/cookstream/video/upload.php" class="btn btn-primary">+ Upload New</a>
  </div>

  <?php if (empty($videos)): ?>
    <div class="empty-dash">
      <div class="big-icon">🎬</div>
      <h3>No videos yet</h3>
      <p>Upload your first cooking video to get started!</p>
      <a href="/cookstream/video/upload.php" class="btn btn-primary">Upload Now</a>
    </div>
  <?php else: ?>
    <table class="video-table">
      <thead>
        <tr>
          <th>Video</th>
          <th>Category</th>
          <th>Views</th>
          <th>Likes</th>
          <th>Comments</th>
          <th>Uploaded</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($videos as $v): ?>
        <tr>
          <td>
            <div class="video-title-cell">
              <div class="video-thumb-sm">
                <?php if ($v['thumbnail_path']): ?>
                  <img src="/cookstream/<?= sanitize($v['thumbnail_path']) ?>"
                       alt="<?= sanitize($v['title']) ?>">
                <?php else: ?>
                  🍽
                <?php endif; ?>
              </div>
              <a href="/cookstream/video/watch.php?id=<?= $v['id'] ?>"
                 style="color:#fff;text-decoration:none;font-weight:600;max-width:260px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= sanitize($v['title']) ?>
              </a>
            </div>
          </td>
          <td>
            <?php if ($v['category'] === 'veg'): ?>
              <span class="badge-cat badge-veg">🥦 Veg</span>
            <?php else: ?>
              <span class="badge-cat badge-non-veg">🍗 Non-Veg</span>
            <?php endif; ?>
          </td>
          <td><?= formatViews((int)$v['views']) ?></td>
          <td><?= (int)$v['like_count'] ?></td>
          <td><?= (int)$v['comment_count'] ?></td>
          <td><?= date('d M Y', strtotime($v['created_at'])) ?></td>
          <td>
            <a class="action-link" href="/cookstream/video/watch.php?id=<?= $v['id'] ?>">▶ Watch</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if ($created): ?>
<div class="toast" id="welcome-toast">🎉 Channel created! Welcome to CookStream.</div>
<script>
  setTimeout(() => {
    const t = document.getElementById('welcome-toast');
    if (t) t.style.opacity = '0';
    setTimeout(() => t && t.remove(), 400);
  }, 4000);
</script>
<?php endif; ?>

<script src="/cookstream/assets/js/main.js"></script>
</body>
</html>
