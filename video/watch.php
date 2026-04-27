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

    /* ── Buttons row ── */
    .action-row {
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      margin-top:14px;
      align-items:center;
    }

    .action-btn {
      display:inline-flex;
      align-items:center;
      gap:7px;
      padding:9px 20px;
      border-radius:999px;
      border:1px solid rgba(255,255,255,.12);
      background:rgba(255,255,255,.05);
      color:#fff;
      font-family:'Outfit',sans-serif;
      font-size:.88rem;
      font-weight:600;
      cursor:pointer;
      transition:all .2s;
      text-decoration:none;
      white-space:nowrap;
    }
    .action-btn:hover { background:rgba(255,255,255,.1); border-color:rgba(255,255,255,.25); transform:translateY(-1px); }
    .action-btn:active { transform:translateY(0); }

    /* Like button states */
    .like-btn-inner { display:inline-flex; align-items:center; gap:7px; pointer-events:none; }
    .heart-icon { width:18px; height:18px; flex-shrink:0; transition:transform .15s; }
    #like-btn.liked  { background:rgba(239,68,68,.18); border-color:#ef4444; color:#fca5a5; }
    #like-btn.liked .heart-icon { transform:scale(1.15); }
    #like-btn:not(.liked):hover .heart-icon { transform:scale(1.1); }

    /* Subscribe button states */
    #sub-btn {
      background: linear-gradient(135deg, hsl(25,100%,50%), hsl(10,90%,50%));
      border: none;
      color: #fff;
      box-shadow: 0 4px 14px hsla(25,100%,50%,.35);
      font-size:.9rem;
      padding:10px 22px;
    }
    #sub-btn:hover { box-shadow:0 6px 20px hsla(25,100%,50%,.5); transform:translateY(-2px); }
    #sub-btn.subbed {
      background: rgba(34,197,94,.15);
      border: 1px solid #22c55e;
      color: #86efac;
      box-shadow: 0 4px 14px rgba(34,197,94,.2);
    }
    #sub-btn.subbed:hover { box-shadow:0 6px 20px rgba(34,197,94,.35); }
    #sub-btn.owner-btn {
      background: rgba(255,255,255,.05);
      border: 1px solid rgba(255,255,255,.1);
      color: rgba(255,255,255,.35);
      box-shadow: none;
      cursor: default;
    }
    #sub-btn.owner-btn:hover { transform:none; box-shadow:none; }

    /* Bell animation */
    .bell-icon { display:inline-block; transition:transform .3s; }
    #sub-btn.subbed .bell-icon { animation:bellRing .4s ease; }
    @keyframes bellRing {
      0%   { transform:rotate(0);   }
      25%  { transform:rotate(20deg); }
      50%  { transform:rotate(-18deg); }
      75%  { transform:rotate(12deg); }
      100% { transform:rotate(0);   }
    }

    /* Sub count badge */
    .sub-count {
      font-size:.75rem;
      opacity:.6;
      margin-left:2px;
    }

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

    <!-- Action buttons -->
    <div class="action-row">
      <!-- Like / Unlike button with SVG heart -->
      <button id="like-btn"
              class="action-btn <?= $userLiked ? 'liked' : '' ?>"
              onclick="toggleLike()">
        <span class="like-btn-inner">
          <svg class="heart-icon" viewBox="0 0 24 24" fill="<?= $userLiked ? '#ef4444' : 'none' ?>"
               stroke="<?= $userLiked ? '#ef4444' : 'rgba(255,255,255,0.7)' ?>"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
          </svg>
          <span id="like-count"><?= $likeCount ?></span>
          <span id="like-label"><?= $userLiked ? 'Unlike' : 'Like' ?></span>
        </span>
      </button>

      <a class="action-btn" href="/cookstream/"
         style="border-color:rgba(255,255,255,.1);color:rgba(255,255,255,.55);">← Back</a>
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

// ── Like / Unlike
async function toggleLike() {
  <?php if (!$user): ?>
    location.href = '/cookstream/auth/login.php'; return;
  <?php endif; ?>
  const btn   = document.getElementById('like-btn');
  const cnt   = document.getElementById('like-count');
  const label = document.getElementById('like-label');
  const svg   = btn.querySelector('.heart-icon');

  const res  = await fetch('/cookstream/api/like.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'video_id=<?= $id ?>'
  });
  const data = await res.json();
  if (data.liked !== undefined) {
    cnt.textContent   = data.count;
    label.textContent = data.liked ? 'Unlike' : 'Like';
    svg.setAttribute('fill',   data.liked ? '#ef4444' : 'none');
    svg.setAttribute('stroke', data.liked ? '#ef4444' : 'rgba(255,255,255,0.7)');
    btn.className = 'action-btn' + (data.liked ? ' liked' : '');
    showToast(
      data.liked ? '❤️ Added to your likes!' : '🤍 Removed from likes',
      data.liked ? 'like-on' : 'like-off'
    );
  }
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
