<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /cookstream/'); exit; }

// Fetch video + channel
$stmt = $conn->prepare(
    "SELECT v.*, c.name AS channel_name, c.id AS channel_id, c.user_id AS channel_owner_id
     FROM videos v
     JOIN channels c ON c.id = v.channel_id
     WHERE v.id = ?"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$v = $stmt->get_result()->fetch_assoc();
if (!$v) { header('Location: /cookstream/'); exit; }

// Increment views (once per session per video)
$sessKey = 'viewed_' . $id;
if (empty($_SESSION[$sessKey])) {
    $conn->query("UPDATE videos SET views = views + 1 WHERE id = $id");
    $_SESSION[$sessKey] = true;
    $v['views']++;
}

$user = getCurrentUser();

// ── Like logic
$likeCount  = 0;
$userLiked  = false;
$likeStmt   = $conn->prepare("SELECT COUNT(*) AS cnt FROM likes WHERE video_id = ?");
$likeStmt->bind_param('i', $id);
$likeStmt->execute();
$likeCount = (int)$likeStmt->get_result()->fetch_assoc()['cnt'];

if ($user) {
    $ulStmt = $conn->prepare("SELECT id FROM likes WHERE video_id = ? AND user_id = ?");
    $ulStmt->bind_param('ii', $id, $user['id']);
    $ulStmt->execute();
    $userLiked = $ulStmt->get_result()->num_rows > 0;
}

// ── Subscribe logic
$subCount = 0;
$userSubbed = false;
$scStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM subscriptions WHERE channel_id = ?");
$scStmt->bind_param('i', $v['channel_id']);
$scStmt->execute();
$subCount = (int)$scStmt->get_result()->fetch_assoc()['cnt'];

if ($user) {
    $usStmt = $conn->prepare("SELECT id FROM subscriptions WHERE channel_id = ? AND user_id = ?");
    $usStmt->bind_param('ii', $v['channel_id'], $user['id']);
    $usStmt->execute();
    $userSubbed = $usStmt->get_result()->num_rows > 0;
}

// ── Comments
$comStmt = $conn->prepare(
    "SELECT cm.*, u.name AS user_name FROM comments cm
     JOIN users u ON u.id = cm.user_id
     WHERE cm.video_id = ?
     ORDER BY cm.created_at DESC"
);
$comStmt->bind_param('i', $id);
$comStmt->execute();
$comments = $comStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Related videos
$relStmt = $conn->prepare(
    "SELECT v2.id, v2.title, v2.thumbnail_path, v2.views, v2.created_at, c2.name AS channel_name
     FROM videos v2
     JOIN channels c2 ON c2.id = v2.channel_id
     WHERE v2.id != ? AND v2.category = ?
     ORDER BY v2.views DESC LIMIT 8"
);
$relStmt->bind_param('is', $id, $v['category']);
$relStmt->execute();
$related = $relStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$ingredients = decodeJson($v['ingredients']);
$steps       = decodeJson($v['steps']);
$uploaded    = isset($_GET['uploaded']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= sanitize($v['title']) ?> – CookStream</title>
  <meta name="description" content="Watch <?= sanitize($v['title']) ?> by <?= sanitize($v['channel_name']) ?> on CookStream.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/cookstream/assets/css/style.css">
  <style>
    body { font-family:'Outfit',sans-serif; }

    .watch-page {
      max-width:1280px;
      margin:0 auto;
      padding:24px 20px 60px;
      display:grid;
      grid-template-columns:1fr 360px;
      gap:28px;
    }
    @media(max-width:900px){ .watch-page{grid-template-columns:1fr;} }

    /* ── Player ── */
    .player-wrap {
      background:#000;
      border-radius:18px;
      overflow:hidden;
      aspect-ratio:16/9;
      position:relative;
    }
    .player-wrap video {
      width:100%;
      height:100%;
      display:block;
    }

    /* ── Video Info ── */
    .video-meta-row {
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
      margin-top:16px;
    }
    .video-title {
      font-size:1.4rem;
      font-weight:800;
      color:#fff;
      margin:0 0 8px;
      line-height:1.3;
    }
    .meta-pill {
      display:inline-flex;
      align-items:center;
      gap:5px;
      color:rgba(255,255,255,.4);
      font-size:.83rem;
      margin-right:14px;
    }

    /* ── YouTube-style Action Bar ── */
    .action-row {
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      margin-top:16px;
      align-items:center;
    }

    /* Generic pill button */
    .yt-pill {
      display:inline-flex;
      align-items:center;
      gap:7px;
      padding:10px 18px;
      border-radius:999px;
      background:rgba(255,255,255,.08);
      border:none;
      color:#fff;
      font-family:'Outfit',sans-serif;
      font-size:.9rem;
      font-weight:600;
      cursor:pointer;
      transition:background .18s, transform .15s;
      text-decoration:none;
      white-space:nowrap;
      vertical-align:middle;
    }
    .yt-pill:hover  { background:rgba(255,255,255,.14); transform:translateY(-1px); }
    .yt-pill:active { background:rgba(255,255,255,.18); transform:translateY(0); }

    /* Like‑dislike grouped pill */
    .like-group {
      display:inline-flex;
      align-items:center;
      border-radius:999px;
      background:rgba(255,255,255,.08);
      overflow:hidden;
    }
    .like-group-btn {
      display:inline-flex;
      align-items:center;
      gap:7px;
      padding:10px 16px;
      border:none;
      background:transparent;
      color:#fff;
      font-family:'Outfit',sans-serif;
      font-size:.9rem;
      font-weight:600;
      cursor:pointer;
      transition:background .18s;
      white-space:nowrap;
    }
    .like-group-btn:hover { background:rgba(255,255,255,.1); }
    .like-group-btn:active{ background:rgba(255,255,255,.18); }
    .like-group-btn.active svg { fill:#fff; }
    .like-group-btn.liked svg  { fill:hsl(25,100%,60%); stroke:hsl(25,100%,60%); }
    .like-group-divider {
      width:1px;
      height:24px;
      background:rgba(255,255,255,.15);
      flex-shrink:0;
    }
    .yt-icon { width:20px; height:20px; flex-shrink:0; }

    /* Subscribe button — YouTube-style pill */
    #sub-btn {
      background: #fff;
      border: none;
      border-radius: 999px !important;
      color: #0f0f0f;
      font-size: .9rem;
      font-weight: 700;
      padding: 10px 20px;
      box-shadow: none;
      letter-spacing: .01em;
      gap: 6px;
    }
    #sub-btn:hover {
      background: #e5e5e5;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0,0,0,.3);
    }
    #sub-btn:active { transform: translateY(0); }

    /* Subscribed state — muted dark pill */
    #sub-btn.subbed {
      background: rgba(255,255,255,.1);
      border: 1px solid rgba(255,255,255,.18) !important;
      color: #fff;
      box-shadow: none;
    }
    #sub-btn.subbed:hover {
      background: rgba(255,255,255,.15);
      transform: translateY(-1px);
    }

    /* Owner state — greyed disabled */
    #sub-btn.owner-btn {
      background: rgba(255,255,255,.08);
      border: 1px solid rgba(255,255,255,.1) !important;
      color: rgba(255,255,255,.35);
      box-shadow: none;
      cursor: default;
      border-radius: 999px !important;
    }
    #sub-btn.owner-btn:hover { transform: none; background: rgba(255,255,255,.08); }

    /* Bell animation */
    .bell-icon { display:inline-block; }
    #sub-btn.subbed .bell-icon { animation: bellRing .4s ease; }
    @keyframes bellRing {
      0%   { transform:rotate(0); }
      25%  { transform:rotate(20deg); }
      50%  { transform:rotate(-18deg); }
      75%  { transform:rotate(12deg); }
      100% { transform:rotate(0); }
    }

    /* Sub count badge */
    .sub-count {
      font-size: .75rem;
      opacity: .55;
      margin-left: 2px;
    }

    /* More menu items */
    .more-item {
      display:flex;
      align-items:center;
      gap:10px;
      padding:10px 14px;
      border-radius:10px;
      color:rgba(255,255,255,.8);
      font-size:.88rem;
      font-weight:500;
      text-decoration:none;
      transition:background .15s;
      font-family:'Outfit',sans-serif;
    }
    .more-item:hover { background:rgba(255,255,255,.07); color:#fff; }

    /* ── Channel Strip ── */
    .channel-strip {
      display:flex;
      align-items:center;
      gap:14px;
      padding:16px 0;
      border-top:1px solid rgba(255,255,255,.07);
      border-bottom:1px solid rgba(255,255,255,.07);
      margin:16px 0;
      flex-wrap:wrap;
    }
    .ch-avatar {
      width:52px;height:52px;
      border-radius:50%;
      background:linear-gradient(135deg,hsl(25,100%,55%),hsl(10,90%,55%));
      display:flex;align-items:center;justify-content:center;
      font-size:1.4rem;font-weight:800;color:#fff;flex-shrink:0;
      box-shadow:0 4px 14px hsla(25,100%,50%,.3);
    }
    .ch-info { flex:1; min-width:0; }
    .ch-name { font-size:1.05rem; font-weight:700; color:#fff; }
    .ch-subs  { font-size:.8rem; color:rgba(255,255,255,.38); margin-top:2px; }

    /* Toast notification */
    .action-toast {
      position:fixed;bottom:30px;right:30px;
      padding:13px 20px;
      border-radius:12px;
      font-weight:600;font-size:.88rem;
      box-shadow:0 8px 24px rgba(0,0,0,.4);
      z-index:9999;
      animation:toastIn .35s cubic-bezier(.23,1,.32,1) both;
      display:flex;align-items:center;gap:8px;
      pointer-events:none;
    }
    .action-toast.sub-on  { background:rgba(34,197,94,.92); color:#fff; }
    .action-toast.sub-off { background:rgba(100,100,120,.92); color:#fff; }
    .action-toast.like-on { background:rgba(239,68,68,.92); color:#fff; }
    .action-toast.like-off{ background:rgba(100,100,120,.92); color:#fff; }

    /* ── Tabs ── */
    .tabs {
      display:flex;
      gap:4px;
      border-bottom:1px solid rgba(255,255,255,.08);
      margin-bottom:18px;
    }
    .tab-btn {
      padding:10px 18px;
      background:transparent;
      border:none;
      border-bottom:2px solid transparent;
      color:rgba(255,255,255,.4);
      font-family:'Outfit',sans-serif;
      font-size:.9rem;
      font-weight:600;
      cursor:pointer;
      transition:color .2s, border-color .2s;
      margin-bottom:-1px;
    }
    .tab-btn.active { color:#fff; border-bottom-color:hsl(25,100%,60%); }

    .tab-panel { display:none; }
    .tab-panel.active { display:block; }

    /* Ingredients & Steps */
    .ingredient-list {
      list-style:none;
      padding:0;margin:0;
      display:grid;
      grid-template-columns:repeat(auto-fill,minmax(200px,1fr));
      gap:8px;
    }
    .ingredient-list li {
      background:rgba(255,255,255,.05);
      border:1px solid rgba(255,255,255,.08);
      border-radius:10px;
      padding:10px 14px;
      font-size:.88rem;
      color:rgba(255,255,255,.75);
      display:flex;
      align-items:center;
      gap:8px;
    }
    .ingredient-list li::before { content:'•'; color:hsl(25,100%,60%); font-size:1.2rem; }

    .steps-list {
      list-style:none;
      padding:0;margin:0;
      display:flex;
      flex-direction:column;
      gap:12px;
    }
    .steps-list li {
      display:flex;
      gap:14px;
      align-items:flex-start;
    }
    .step-num {
      min-width:32px;height:32px;
      border-radius:50%;
      background:linear-gradient(135deg,hsl(25,100%,55%),hsl(10,90%,55%));
      display:flex;align-items:center;justify-content:center;
      font-weight:800;font-size:.82rem;color:#fff;flex-shrink:0;
      margin-top:2px;
    }
    .step-text { font-size:.9rem; color:rgba(255,255,255,.72); line-height:1.55; }

    /* ── Comments ── */
    .comment-form { margin-bottom:24px; }
    .comment-form textarea {
      width:100%;
      padding:12px 15px;
      background:rgba(255,255,255,.06);
      border:1px solid rgba(255,255,255,.1);
      border-radius:12px;
      color:#fff;
      font-family:'Outfit',sans-serif;
      font-size:.9rem;
      resize:none;
      outline:none;
      transition:border-color .2s;
      box-sizing:border-box;
      min-height:80px;
    }
    .comment-form textarea:focus { border-color:hsl(25,100%,60%); }
    .comment-form button {
      margin-top:8px;
      padding:9px 20px;
      background:linear-gradient(135deg,hsl(25,100%,55%),hsl(10,90%,55%));
      color:#fff;
      border:none;
      border-radius:10px;
      font-family:'Outfit',sans-serif;
      font-size:.88rem;
      font-weight:700;
      cursor:pointer;
      transition:opacity .15s;
    }
    .comment-form button:hover { opacity:.85; }

    .comment-item {
      display:flex;
      gap:12px;
      margin-bottom:18px;
    }
    .comment-avatar {
      width:36px;height:36px;
      border-radius:50%;
      background:rgba(255,255,255,.1);
      display:flex;align-items:center;justify-content:center;
      font-weight:700;font-size:.9rem;color:#fff;flex-shrink:0;
    }
    .comment-body {}
    .comment-user { font-size:.82rem; font-weight:700; color:#fff; margin-bottom:3px; }
    .comment-text { font-size:.88rem; color:rgba(255,255,255,.65); line-height:1.45; }
    .comment-time { font-size:.75rem; color:rgba(255,255,255,.28); margin-top:3px; }

    /* ── Sidebar ── */
    .related-title {
      font-size:.78rem;
      font-weight:700;
      text-transform:uppercase;
      letter-spacing:.07em;
      color:rgba(255,255,255,.35);
      margin:0 0 14px;
    }

    .rel-card {
      display:flex;
      gap:12px;
      margin-bottom:14px;
      text-decoration:none;
      transition:background .2s;
      border-radius:12px;
      padding:6px;
    }
    .rel-card:hover { background:rgba(255,255,255,.04); }
    .rel-thumb {
      width:110px;height:62px;
      border-radius:8px;
      overflow:hidden;
      flex-shrink:0;
      background:rgba(255,255,255,.07);
      display:flex;align-items:center;justify-content:center;
      font-size:1.5rem;
    }
    .rel-thumb img { width:100%;height:100%;object-fit:cover; }
    .rel-info {}
    .rel-title { font-size:.84rem; font-weight:600; color:#fff; line-height:1.3; margin-bottom:4px; }
    .rel-channel { font-size:.76rem; color:rgba(255,255,255,.38); }
    .rel-views   { font-size:.74rem; color:rgba(255,255,255,.28); margin-top:2px; }

    /* Toast */
    .toast {
      position:fixed;bottom:30px;right:30px;
      background:rgba(34,197,94,.9);color:#fff;
      padding:14px 20px;border-radius:12px;
      font-weight:600;font-size:.9rem;
      box-shadow:0 8px 24px rgba(0,0,0,.4);
      animation:toastIn .4s ease both;
      z-index:9999;
    }
    @keyframes toastIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}

    /* Description */
    .description-text {
      font-size:.9rem;
      color:rgba(255,255,255,.6);
      line-height:1.65;
      white-space:pre-wrap;
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
  <a class="navbar-brand" href="/cookstream/">
    <span class="logo-icon">🍳</span> CookStream
  </a>
  <div class="nav-actions">
    <?php if ($user): ?>
      <?php $myChannel = getUserChannel($conn); ?>
      <?php if ($myChannel): ?>
        <a href="/cookstream/video/upload.php" class="btn btn-primary">⬆ Upload</a>
        <a href="/cookstream/channel/dashboard.php" class="btn btn-outline">📺 My Channel</a>
      <?php else: ?>
        <a href="/cookstream/channel/create.php" class="btn btn-outline">＋ Create Channel</a>
      <?php endif; ?>
      <a href="/cookstream/auth/logout.php" class="btn btn-ghost">Sign Out</a>
      <div class="avatar"><?= strtoupper($user['name'][0]) ?></div>
    <?php else: ?>
      <a href="/cookstream/auth/login.php" class="btn btn-outline">Sign In</a>
      <a href="/cookstream/auth/register.php" class="btn btn-primary">Join Free</a>
    <?php endif; ?>
  </div>
</nav>

<div class="watch-page">
  <!-- ── Main column ── -->
  <div>
    <!-- Player -->
    <div class="player-wrap">
      <video controls autoplay preload="metadata"
             poster="<?= $v['thumbnail_path'] ? '/cookstream/' . sanitize($v['thumbnail_path']) : '' ?>">
        <source src="/cookstream/<?= sanitize($v['video_path']) ?>" type="video/mp4">
        Your browser does not support video playback.
      </video>
    </div>

    <!-- Title & meta -->
    <h1 class="video-title" style="margin-top:16px"><?= sanitize($v['title']) ?></h1>
    <div>
      <span class="meta-pill">👁 <?= formatViews((int)$v['views']) ?> views</span>
      <span class="meta-pill">📅 <?= timeAgo($v['created_at']) ?></span>
      <?= vegBadge($v['category']) ?>
    </div>

    <!-- Action bar — YouTube style -->
    <div class="action-row">

      <!-- 👍 Like / 👎 Dislike grouped pill -->
      <div class="like-group">
        <!-- Thumbs Up -->
        <button id="like-btn"
                class="like-group-btn <?= $userLiked ? 'liked' : '' ?>"
                onclick="toggleLike()"
                title="<?= $userLiked ? 'Unlike' : 'Like' ?> this video">
          <svg class="yt-icon" viewBox="0 0 24 24"
               fill="<?= $userLiked ? 'hsl(25,100%,60%)' : 'none' ?>"
               stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3H14z"/>
            <path d="M7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>
          </svg>
          <span id="like-count"><?= $likeCount ?></span>
        </button>

        <div class="like-group-divider"></div>

        <!-- Thumbs Down -->
        <button id="dislike-btn"
                class="like-group-btn <?= $userDisliked ?? false ? 'liked' : '' ?>"
                onclick="toggleDislike()"
                title="Dislike this video">
          <svg class="yt-icon" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round">
            <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3H10z"/>
            <path d="M17 2h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"/>
          </svg>
        </button>
      </div>

      <!-- Share pill -->
      <button class="yt-pill" onclick="shareVideo()" title="Share">
        <svg class="yt-icon" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/>
          <circle cx="18" cy="19" r="3"/>
          <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
          <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
        </svg>
        Share
      </button>

      <!-- More options pill -->
      <button class="yt-pill" id="more-btn" onclick="toggleMoreMenu()" title="More">
        <svg class="yt-icon" viewBox="0 0 24 24" fill="currentColor">
          <circle cx="5"  cy="12" r="1.5"/>
          <circle cx="12" cy="12" r="1.5"/>
          <circle cx="19" cy="12" r="1.5"/>
        </svg>
      </button>

      <!-- More dropdown -->
      <div id="more-menu" style="
        display:none;position:absolute;margin-top:4px;
        background:rgba(30,30,40,.97);border:1px solid rgba(255,255,255,.1);
        border-radius:14px;padding:8px;min-width:180px;
        box-shadow:0 12px 36px rgba(0,0,0,.5);z-index:100;
      ">
        <a href="/cookstream/" class="more-item">← Back to Home</a>
        <?php if ($user && $myChannel): ?>
          <a href="/cookstream/channel/dashboard.php" class="more-item">📺 My Channel</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Share toast / copy-link popup -->
    <div id="share-toast" style="
      display:none;position:fixed;bottom:30px;left:50%;transform:translateX(-50%);
      background:rgba(30,30,40,.96);border:1px solid rgba(255,255,255,.12);
      border-radius:14px;padding:14px 18px;z-index:9999;
      box-shadow:0 12px 36px rgba(0,0,0,.5);
      display:none;align-items:center;gap:10px;min-width:320px;
    ">
      <input id="share-url" type="text" readonly
             value="<?= SITE_URL ?>/video/watch.php?id=<?= $id ?>"
             style="flex:1;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);
                    border-radius:8px;padding:8px 12px;color:#fff;font-size:.85rem;
                    font-family:'Outfit',sans-serif;outline:none;">
      <button onclick="copyLink()" style="
        padding:8px 16px;border-radius:8px;background:hsl(25,100%,55%);
        border:none;color:#fff;font-family:'Outfit',sans-serif;
        font-weight:700;font-size:.85rem;cursor:pointer;white-space:nowrap;">
        Copy Link
      </button>
      <button onclick="closeShare()" style="
        background:none;border:none;color:rgba(255,255,255,.4);
        cursor:pointer;font-size:1.2rem;padding:0 4px;">✕</button>
    </div>

    <!-- Channel strip with Subscribe button -->
    <div class="channel-strip">
      <div class="ch-avatar"><?= strtoupper($v['channel_name'][0]) ?></div>
      <div class="ch-info">
        <div class="ch-name"><?= sanitize($v['channel_name']) ?></div>
        <div class="ch-subs" id="sub-count-strip"><?= formatViews($subCount) ?> subscribers</div>
      </div>

      <?php if (!$user): ?>
        <!-- Not logged in → redirect to login -->
        <button id="sub-btn" onclick="location.href='/cookstream/auth/login.php'">
          <span class="bell-icon">🔔</span> Subscribe
        </button>

      <?php elseif ($user['id'] === $v['channel_owner_id']): ?>
        <!-- Own channel → disabled -->
        <button id="sub-btn" class="owner-btn" disabled>
          📺 Your Channel
        </button>

      <?php else: ?>
        <!-- Other user → subscribe toggle -->
        <button id="sub-btn"
                class="<?= $userSubbed ? 'subbed' : '' ?>"
                onclick="toggleSub()">
          <span class="bell-icon"><?= $userSubbed ? '🔔' : '🔕' ?></span>
          <span id="sub-label"><?= $userSubbed ? 'Subscribed' : 'Subscribe' ?></span>
          <span class="sub-count" id="sub-count-btn"><?= formatViews($subCount) ?></span>
        </button>
      <?php endif; ?>
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <button class="tab-btn active" onclick="switchTab('desc',this)">📄 Description</button>
      <?php if ($ingredients): ?>
        <button class="tab-btn" onclick="switchTab('ing',this)">🥗 Ingredients (<?= count($ingredients) ?>)</button>
      <?php endif; ?>
      <?php if ($steps): ?>
        <button class="tab-btn" onclick="switchTab('steps',this)">👨‍🍳 Steps (<?= count($steps) ?>)</button>
      <?php endif; ?>
      <button class="tab-btn" onclick="switchTab('comments',this)">💬 Comments (<?= count($comments) ?>)</button>
    </div>

    <!-- Description -->
    <div class="tab-panel active" id="tab-desc">
      <?php if ($v['description']): ?>
        <p class="description-text"><?= sanitize($v['description']) ?></p>
      <?php else: ?>
        <p style="color:rgba(255,255,255,.25);font-size:.88rem">No description provided.</p>
      <?php endif; ?>
    </div>

    <!-- Ingredients -->
    <?php if ($ingredients): ?>
    <div class="tab-panel" id="tab-ing">
      <ul class="ingredient-list">
        <?php foreach ($ingredients as $ing): ?>
          <li><?= sanitize($ing) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <!-- Steps -->
    <?php if ($steps): ?>
    <div class="tab-panel" id="tab-steps">
      <ol class="steps-list">
        <?php foreach ($steps as $i => $step): ?>
          <li>
            <div class="step-num"><?= $i + 1 ?></div>
            <div class="step-text"><?= sanitize($step) ?></div>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
    <?php endif; ?>

    <!-- Comments -->
    <div class="tab-panel" id="tab-comments">
      <?php if ($user): ?>
      <form class="comment-form" method="POST" action="/cookstream/api/comment.php"
            onsubmit="submitComment(event)">
        <input type="hidden" name="video_id" value="<?= $id ?>">
        <textarea name="comment" id="comment-text" placeholder="Share your thoughts or ask a question…" required></textarea>
        <button type="submit">💬 Post Comment</button>
      </form>
      <?php else: ?>
        <p style="color:rgba(255,255,255,.3);font-size:.88rem">
          <a href="/cookstream/auth/login.php" style="color:hsl(25,100%,60%)">Sign in</a> to leave a comment.
        </p>
      <?php endif; ?>

      <div id="comments-list">
        <?php foreach ($comments as $c): ?>
        <div class="comment-item">
          <div class="comment-avatar"><?= strtoupper($c['user_name'][0]) ?></div>
          <div class="comment-body">
            <div class="comment-user"><?= sanitize($c['user_name']) ?></div>
            <div class="comment-text"><?= sanitize($c['comment']) ?></div>
            <div class="comment-time"><?= timeAgo($c['created_at']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($comments)): ?>
          <p style="color:rgba(255,255,255,.25);font-size:.88rem">No comments yet. Be the first!</p>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /main -->

  <!-- ── Sidebar ── -->
  <div>
    <p class="related-title">Up Next</p>
    <?php foreach ($related as $r): ?>
    <a class="rel-card" href="/cookstream/video/watch.php?id=<?= $r['id'] ?>">
      <div class="rel-thumb">
        <?php if ($r['thumbnail_path']): ?>
          <img src="/cookstream/<?= sanitize($r['thumbnail_path']) ?>" alt="">
        <?php else: ?>
          🍽
        <?php endif; ?>
      </div>
      <div class="rel-info">
        <div class="rel-title"><?= sanitize($r['title']) ?></div>
        <div class="rel-channel"><?= sanitize($r['channel_name']) ?></div>
        <div class="rel-views">👁 <?= formatViews((int)$r['views']) ?> views · <?= timeAgo($r['created_at']) ?></div>
      </div>
    </a>
    <?php endforeach; ?>
    <?php if (empty($related)): ?>
      <p style="color:rgba(255,255,255,.25);font-size:.82rem">No related videos yet.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Toast on upload -->
<?php if ($uploaded): ?>
<div class="toast" id="upload-toast">🎉 Video published successfully!</div>
<script>
  setTimeout(() => {
    const t = document.getElementById('upload-toast');
    if (t) { t.style.opacity='0'; setTimeout(()=>t.remove(),400); }
  }, 4000);
</script>
<?php endif; ?>

<script>
// ── Tabs
function switchTab(name, btn) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  const panel = document.getElementById('tab-' + name);
  if (panel) panel.classList.add('active');
  btn.classList.add('active');
}

// ── Toast helper
function showToast(msg, cls) {
  document.querySelectorAll('.action-toast').forEach(t => t.remove());
  const t = document.createElement('div');
  t.className = 'action-toast ' + cls;
  t.innerHTML = msg;
  document.body.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .4s'; setTimeout(()=>t.remove(),400); }, 3000);
}

// ── Like / Unlike (thumbs up)
async function toggleLike() {
  <?php if (!$user): ?>
    location.href = '/cookstream/auth/login.php'; return;
  <?php endif; ?>
  const btn  = document.getElementById('like-btn');
  const cnt  = document.getElementById('like-count');
  const svg  = btn.querySelector('svg');

  const res  = await fetch('/cookstream/api/like.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'video_id=<?= $id ?>'
  });
  const data = await res.json();
  if (data.liked !== undefined) {
    cnt.textContent = data.count;
    svg.setAttribute('fill', data.liked ? 'hsl(25,100%,60%)' : 'none');
    svg.setAttribute('stroke', data.liked ? 'hsl(25,100%,60%)' : 'currentColor');
    btn.className = 'like-group-btn' + (data.liked ? ' liked' : '');
    btn.title     = data.liked ? 'Unlike this video' : 'Like this video';
    showToast(
      data.liked ? '👍 You liked this video!' : '👎 Like removed',
      data.liked ? 'like-on' : 'like-off'
    );
  }
}

// ── Dislike (visual only — no count stored)
function toggleDislike() {
  <?php if (!$user): ?>
    location.href = '/cookstream/auth/login.php'; return;
  <?php endif; ?>
  const btn = document.getElementById('dislike-btn');
  const isActive = btn.classList.contains('liked');
  btn.classList.toggle('liked');
  // Remove like if disliking
  if (!isActive) {
    const likeBtn = document.getElementById('like-btn');
    if (likeBtn.classList.contains('liked')) toggleLike();
  }
  showToast(
    isActive ? '' : '👎 Thanks for your feedback.',
    'like-off'
  );
}

// ── Subscribe / Unsubscribe
async function toggleSub() {
  const btn      = document.getElementById('sub-btn');
  const label    = document.getElementById('sub-label');
  const bell     = btn.querySelector('.bell-icon');
  const cntBtn   = document.getElementById('sub-count-btn');
  const cntStrip = document.getElementById('sub-count-strip');

  const res  = await fetch('/cookstream/api/subscribe.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'channel_id=<?= $v['channel_id'] ?>'
  });
  const data = await res.json();
  if (data.subscribed !== undefined) {
    label.textContent    = data.subscribed ? 'Subscribed' : 'Subscribe';
    bell.textContent     = data.subscribed ? '🔔' : '🔕';
    if (cntBtn)   cntBtn.textContent   = data.count;
    if (cntStrip) cntStrip.textContent = data.count + ' subscribers';
    btn.className = data.subscribed ? 'subbed' : '';
    showToast(
      data.subscribed
        ? '🔔 Subscribed! You\'ll be notified of new videos.'
        : '🔕 Unsubscribed from this channel.',
      data.subscribed ? 'sub-on' : 'sub-off'
    );
  }
}

// ── Share
let shareOpen = false;
function shareVideo() {
  const st = document.getElementById('share-toast');
  shareOpen = !shareOpen;
  st.style.display = shareOpen ? 'flex' : 'none';
  if (shareOpen) document.getElementById('share-url').select();
}
function closeShare() {
  document.getElementById('share-toast').style.display = 'none';
  shareOpen = false;
}
async function copyLink() {
  const url = document.getElementById('share-url').value;
  try {
    await navigator.clipboard.writeText(url);
    showToast('🔗 Link copied to clipboard!', 'sub-on');
    closeShare();
  } catch { document.getElementById('share-url').select(); }
}

// ── More menu
let moreOpen = false;
function toggleMoreMenu() {
  const menu = document.getElementById('more-menu');
  const btn  = document.getElementById('more-btn');
  moreOpen = !moreOpen;
  menu.style.display = moreOpen ? 'block' : 'none';
  if (moreOpen) {
    const r = btn.getBoundingClientRect();
    menu.style.position = 'fixed';
    menu.style.top  = (r.bottom + 6) + 'px';
    menu.style.left = r.left + 'px';
  }
}
document.addEventListener('click', e => {
  if (!e.target.closest('#more-btn') && !e.target.closest('#more-menu')) {
    document.getElementById('more-menu').style.display = 'none';
    moreOpen = false;
  }
});

// ── Comment
async function submitComment(e) {
  e.preventDefault();
  const txt  = document.getElementById('comment-text');
  const list = document.getElementById('comments-list');
  if (!txt.value.trim()) return;
  const res = await fetch('/cookstream/api/comment.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'video_id=<?= $id ?>&comment=' + encodeURIComponent(txt.value)
  });
  const data = await res.json();
  if (data.success) {
    const html = `<div class="comment-item">
      <div class="comment-avatar">${data.initial}</div>
      <div class="comment-body">
        <div class="comment-user">${data.name}</div>
        <div class="comment-text">${data.comment}</div>
        <div class="comment-time">just now</div>
      </div>
    </div>`;
    list.insertAdjacentHTML('afterbegin', html);
    txt.value = '';
  }
}
</script>
</body>
</html>
