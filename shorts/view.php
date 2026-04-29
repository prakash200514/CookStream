<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$startId = (int)($_GET['id'] ?? 0);

// Load all shorts, start from clicked one
$all = $conn->prepare(
    "SELECT s.*, c.name AS channel_name, c.id AS channel_id
     FROM shorts s JOIN channels c ON c.id = s.channel_id
     ORDER BY s.created_at DESC LIMIT 30"
);
$all->execute();
$shorts = $all->get_result()->fetch_all(MYSQLI_ASSOC);

// Reorder: put clicked short first
if ($startId) {
    usort($shorts, fn($a,$b) => ($b['id']==$startId) - ($a['id']==$startId));
}

$user     = getCurrentUser();
$uploaded = isset($_GET['uploaded']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>CookStream Shorts</title>
  <meta name="description" content="Watch short cooking videos on CookStream Shorts.">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/cookstream/assets/css/style.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Outfit', sans-serif; background: #000; overflow: hidden; }

    /* ── Top bar ── */
    .shorts-topbar {
      position: fixed; top: 0; left: 0; right: 0; z-index: 100;
      display: flex; align-items: center; justify-content: space-between;
      padding: 14px 20px;
      background: linear-gradient(to bottom, rgba(0,0,0,.7) 0%, transparent 100%);
    }
    .shorts-logo { font-size: 1.1rem; font-weight: 800; color: #fff; text-decoration: none; display: flex; align-items: center; gap: 8px; }
    .shorts-badge {
      background: linear-gradient(135deg, #a855f7, #ec4899);
      padding: 2px 10px; border-radius: 20px; font-size: .72rem; font-weight: 700;
    }
    .topbar-right { display: flex; gap: 10px; align-items: center; }

    /* ── Feed container ── */
    .shorts-feed {
      height: 100vh; overflow-y: scroll; scroll-snap-type: y mandatory;
      scrollbar-width: none;
    }
    .shorts-feed::-webkit-scrollbar { display: none; }

    /* ── Each short item ── */
    .short-item {
      height: 100vh; scroll-snap-align: start;
      display: flex; justify-content: center; align-items: center;
      position: relative; background: #000;
    }

    /* Video */
    .short-video {
      height: 100%; max-width: 400px; width: 100%;
      object-fit: cover; display: block;
    }

    /* Gradient overlays */
    .short-item::before {
      content: ''; position: absolute; inset: 0;
      background: linear-gradient(to top, rgba(0,0,0,.75) 0%, transparent 40%, transparent 70%, rgba(0,0,0,.4) 100%);
      pointer-events: none; z-index: 1;
    }

    /* ── Bottom info ── */
    .short-info {
      position: absolute; bottom: 80px; left: 0; right: 70px;
      padding: 0 16px; z-index: 2;
    }
    .short-channel {
      display: flex; align-items: center; gap: 10px; margin-bottom: 10px;
    }
    .short-avatar {
      width: 38px; height: 38px; border-radius: 50%; border: 2px solid #fff;
      background: linear-gradient(135deg,hsl(25,100%,55%),hsl(10,90%,55%));
      display: flex; align-items: center; justify-content: center;
      font-weight: 800; font-size: .95rem; color: #fff; flex-shrink: 0;
    }
    .short-channel-name { font-weight: 700; color: #fff; font-size: .9rem; }
    .short-title { font-size: 1rem; font-weight: 700; color: #fff; margin-bottom: 6px; line-height: 1.3; }
    .short-desc  { font-size: .82rem; color: rgba(255,255,255,.65); line-height: 1.4; }

    /* ── Right action bar ── */
    .short-actions {
      position: absolute; right: 10px; bottom: 90px;
      display: flex; flex-direction: column; align-items: center; gap: 20px;
      z-index: 2;
    }
    .action-item {
      display: flex; flex-direction: column; align-items: center; gap: 4px;
      cursor: pointer; border: none; background: transparent; color: #fff;
      font-family: 'Outfit', sans-serif;
    }
    .action-item .icon-wrap {
      width: 46px; height: 46px; border-radius: 50%;
      background: rgba(255,255,255,.15); backdrop-filter: blur(6px);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.4rem; transition: transform .15s, background .2s;
    }
    .action-item:hover .icon-wrap { background: rgba(255,255,255,.25); transform: scale(1.1); }
    .action-item.liked .icon-wrap { background: rgba(236,72,153,.35); }
    .action-item span { font-size: .75rem; font-weight: 600; }

    /* ── Play/Pause tap overlay ── */
    .tap-overlay {
      position: absolute; inset: 0; z-index: 1; cursor: pointer;
    }

    /* ── Comments panel ── */
    .comments-panel {
      position: fixed; bottom: 0; left: 50%; transform: translateX(-50%) translateY(100%);
      width: 100%; max-width: 400px;
      background: rgba(18,18,24,.97); border-radius: 20px 20px 0 0;
      border-top: 1px solid rgba(255,255,255,.1);
      z-index: 200; transition: transform .35s cubic-bezier(.23,1,.32,1);
      max-height: 70vh; display: flex; flex-direction: column;
    }
    .comments-panel.open { transform: translateX(-50%) translateY(0); }
    .panel-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 16px 18px 10px; border-bottom: 1px solid rgba(255,255,255,.07);
    }
    .panel-header h3 { font-size: .95rem; font-weight: 700; color: #fff; }
    .panel-close { background: none; border: none; color: rgba(255,255,255,.5); font-size: 1.3rem; cursor: pointer; }
    .comments-list { overflow-y: auto; flex: 1; padding: 12px 18px; }
    .comment-item { display: flex; gap: 10px; margin-bottom: 14px; }
    .c-avatar {
      width: 30px; height: 30px; border-radius: 50%; background: rgba(255,255,255,.1);
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: .78rem; color: #fff; flex-shrink: 0;
    }
    .c-name { font-size: .78rem; font-weight: 700; color: #fff; }
    .c-text { font-size: .82rem; color: rgba(255,255,255,.65); margin-top: 2px; line-height: 1.4; }
    .comment-input-row {
      display: flex; gap: 10px; padding: 12px 18px;
      border-top: 1px solid rgba(255,255,255,.07);
    }
    .comment-input-row input {
      flex: 1; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12);
      border-radius: 999px; padding: 9px 16px; color: #fff;
      font-family: 'Outfit',sans-serif; font-size: .88rem; outline: none;
    }
    .comment-input-row button {
      background: linear-gradient(135deg,#a855f7,#ec4899); border: none;
      border-radius: 999px; padding: 9px 18px; color: #fff;
      font-weight: 700; font-size: .85rem; cursor: pointer; white-space: nowrap;
    }

    /* backdrop */
    .panel-backdrop {
      display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 199;
    }
    .panel-backdrop.open { display: block; }

    /* ── Toast ── */
    .s-toast {
      position: fixed; bottom: 90px; left: 50%; transform: translateX(-50%);
      background: rgba(30,30,40,.95); color: #fff; padding: 10px 20px;
      border-radius: 999px; font-size: .85rem; font-weight: 600;
      z-index: 300; animation: toastIn .3s ease both; white-space: nowrap;
    }
    @keyframes toastIn { from{opacity:0;transform:translateX(-50%) translateY(10px)} to{opacity:1;transform:translateX(-50%) translateY(0)} }

    /* ── Nav arrows (desktop) ── */
    .nav-arrows {
      position: fixed; right: 20px; top: 50%; transform: translateY(-50%);
      display: flex; flex-direction: column; gap: 10px; z-index: 100;
    }
    .nav-arrow {
      width: 40px; height: 40px; border-radius: 50%;
      background: rgba(255,255,255,.12); backdrop-filter: blur(8px);
      border: none; color: #fff; font-size: 1.1rem; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: background .2s;
    }
    .nav-arrow:hover { background: rgba(255,255,255,.25); }

    /* progress dots */
    .progress-dots {
      position: fixed; right: 70px; top: 50%; transform: translateY(-50%);
      display: flex; flex-direction: column; gap: 6px; z-index: 100;
    }
    .p-dot { width: 4px; height: 4px; border-radius: 2px; background: rgba(255,255,255,.25); transition: all .3s; }
    .p-dot.active { background: #fff; height: 16px; }
  </style>
</head>
<body>

<!-- Top bar -->
<div class="shorts-topbar">
  <a class="shorts-logo" href="/cookstream/">
    🍳 CookStream <span class="shorts-badge">Shorts</span>
  </a>
  <div class="topbar-right">
    <?php if ($user): ?>
      <?php $myChannel = getUserChannel($conn); ?>
      <?php if ($myChannel): ?>
        <a href="/cookstream/shorts/upload.php" class="btn btn-primary" style="padding:7px 16px;font-size:.85rem;">
          + Short
        </a>
      <?php endif; ?>
      <div class="avatar" style="width:34px;height:34px;font-size:.85rem"><?= strtoupper($user['name'][0]) ?></div>
    <?php else: ?>
      <a href="/cookstream/auth/login.php" class="btn btn-outline" style="padding:7px 16px;font-size:.85rem;">Sign In</a>
    <?php endif; ?>
    <a href="/cookstream/" class="btn btn-ghost" style="padding:7px 14px;font-size:.85rem;">← Home</a>
  </div>
</div>

<?php if (empty($shorts)): ?>
  <!-- Empty state -->
  <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;color:rgba(255,255,255,.5);text-align:center;gap:16px;">
    <div style="font-size:4rem">📱</div>
    <h2 style="color:#fff">No Shorts Yet</h2>
    <p>Be the first to upload a cooking short!</p>
    <?php if ($user && getUserChannel($conn)): ?>
      <a href="/cookstream/shorts/upload.php" class="btn btn-primary" style="margin-top:8px">Upload Short</a>
    <?php endif; ?>
  </div>
<?php else: ?>

<!-- Shorts feed -->
<div class="shorts-feed" id="shorts-feed">
  <?php foreach ($shorts as $i => $s): ?>
  <?php
    $likeCount = (int)$s['like_count'];
    $userLiked = false;
    if ($user) {
      $lq = $conn->prepare("SELECT id FROM shorts_likes WHERE short_id=? AND user_id=?");
      $lq->bind_param('ii', $s['id'], $user['id']);
      $lq->execute();
      $userLiked = $lq->get_result()->num_rows > 0;
    }
    $cq = $conn->prepare("SELECT COUNT(*) AS c FROM comments WHERE video_id IS NULL");
    // Count shorts comments (we store in a separate way)
    $cmtCount = 0;
  ?>
  <div class="short-item" data-id="<?= $s['id'] ?>" data-index="<?= $i ?>">

    <!-- Tap to play/pause -->
    <div class="tap-overlay" onclick="togglePlay(<?= $i ?>)"></div>

    <!-- Video -->
    <video class="short-video" id="sv-<?= $i ?>"
           src="/cookstream/<?= sanitize($s['video_path']) ?>"
           loop playsinline preload="none"
           <?= $i === 0 ? 'autoplay muted' : 'muted' ?>></video>

    <!-- Info -->
    <div class="short-info">
      <div class="short-channel">
        <div class="short-avatar"><?= strtoupper($s['channel_name'][0]) ?></div>
        <span class="short-channel-name"><?= sanitize($s['channel_name']) ?></span>
      </div>
      <div class="short-title"><?= sanitize($s['title']) ?></div>
      <?php if ($s['description']): ?>
        <div class="short-desc"><?= sanitize(mb_substr($s['description'], 0, 80)) ?><?= mb_strlen($s['description']) > 80 ? '…' : '' ?></div>
      <?php endif; ?>
    </div>

    <!-- Right actions -->
    <div class="short-actions">
      <!-- Like -->
      <button class="action-item <?= $userLiked ? 'liked' : '' ?>"
              id="like-<?= $i ?>"
              onclick="toggleLike(event, <?= $s['id'] ?>, <?= $i ?>)">
        <div class="icon-wrap">
          <?= $userLiked ? '❤️' : '🤍' ?>
        </div>
        <span id="lc-<?= $i ?>"><?= formatViews($likeCount) ?></span>
      </button>

      <!-- Comment -->
      <button class="action-item" onclick="openComments(<?= $s['id'] ?>)">
        <div class="icon-wrap">💬</div>
        <span>Comment</span>
      </button>

      <!-- Share -->
      <button class="action-item" onclick="shareShort(<?= $s['id'] ?>)">
        <div class="icon-wrap">↗️</div>
        <span>Share</span>
      </button>

      <!-- Category badge -->
      <div style="text-align:center;">
        <div style="font-size:1.4rem"><?= $s['category']==='veg'?'🥦':'🍗' ?></div>
        <span style="font-size:.65rem;color:rgba(255,255,255,.5)"><?= $s['category']==='veg'?'Veg':'Non-Veg' ?></span>
      </div>
    </div>

  </div>
  <?php endforeach; ?>
</div>

<!-- Nav arrows -->
<div class="nav-arrows">
  <button class="nav-arrow" onclick="scrollFeed(-1)">▲</button>
  <button class="nav-arrow" onclick="scrollFeed(1)">▼</button>
</div>

<!-- Progress dots -->
<div class="progress-dots" id="progress-dots">
  <?php foreach ($shorts as $i => $s): ?>
    <div class="p-dot <?= $i===0?'active':'' ?>" id="dot-<?= $i ?>"></div>
  <?php endforeach; ?>
</div>

<?php endif; ?>

<!-- Comments panel -->
<div class="panel-backdrop" id="panel-backdrop" onclick="closeComments()"></div>
<div class="comments-panel" id="comments-panel">
  <div class="panel-header">
    <h3>💬 Comments</h3>
    <button class="panel-close" onclick="closeComments()">✕</button>
  </div>
  <div class="comments-list" id="comments-list">
    <p style="color:rgba(255,255,255,.3);font-size:.85rem;text-align:center;padding:20px 0">Loading…</p>
  </div>
  <?php if ($user): ?>
  <div class="comment-input-row">
    <input type="text" id="comment-input" placeholder="Add a comment…" maxlength="500">
    <button onclick="postComment()">Send</button>
  </div>
  <?php else: ?>
  <div style="padding:12px 18px;color:rgba(255,255,255,.35);font-size:.85rem">
    <a href="/cookstream/auth/login.php" style="color:#a855f7">Sign in</a> to comment
  </div>
  <?php endif; ?>
</div>

<?php if ($uploaded): ?>
<div class="s-toast" id="up-toast">🎉 Short published!</div>
<script>setTimeout(()=>{const t=document.getElementById('up-toast');if(t){t.style.opacity=0;setTimeout(()=>t.remove(),400);}},3500);</script>
<?php endif; ?>

<script>
const shorts    = <?= json_encode(array_map(fn($s)=>['id'=>$s['id']],$shorts)) ?>;
const feed      = document.getElementById('shorts-feed');
let   currentIdx = 0;
let   activeCommentShortId = null;
const isLoggedIn = <?= $user ? 'true' : 'false' ?>;

// ── Scroll observer
const observer = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      const idx = +e.target.dataset.index;
      // Pause all, play current
      document.querySelectorAll('.short-video').forEach((v,i) => {
        if (i === idx) { v.play().catch(()=>{}); }
        else { v.pause(); v.currentTime = 0; }
      });
      // Update dots
      document.querySelectorAll('.p-dot').forEach((d,i)=> d.classList.toggle('active', i===idx));
      currentIdx = idx;
    }
  });
}, { threshold: 0.6 });

document.querySelectorAll('.short-item').forEach(el => observer.observe(el));

// ── Play / Pause tap
function togglePlay(idx) {
  const v = document.getElementById('sv-' + idx);
  if (!v) return;
  if (v.paused) v.play();
  else v.pause();
}

// ── Scroll feed
function scrollFeed(dir) {
  const items = document.querySelectorAll('.short-item');
  const next  = Math.max(0, Math.min(items.length - 1, currentIdx + dir));
  items[next].scrollIntoView({ behavior: 'smooth' });
}

// ── Like toggle
async function toggleLike(e, shortId, idx) {
  e.stopPropagation();
  if (!isLoggedIn) { location.href = '/cookstream/auth/login.php'; return; }
  const btn  = document.getElementById('like-' + idx);
  const cnt  = document.getElementById('lc-'   + idx);
  const icon = btn.querySelector('.icon-wrap');
  const res  = await fetch('/cookstream/api/short_like.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: 'short_id=' + shortId
  });
  const data = await res.json();
  if (data.liked !== undefined) {
    icon.textContent  = data.liked ? '❤️' : '🤍';
    cnt.textContent   = data.count;
    btn.classList.toggle('liked', data.liked);
    showToast(data.liked ? '❤️ Liked!' : '🤍 Removed');
  }
}

// ── Comments
async function openComments(shortId) {
  activeCommentShortId = shortId;
  document.getElementById('comments-panel').classList.add('open');
  document.getElementById('panel-backdrop').classList.add('open');
  const list = document.getElementById('comments-list');
  list.innerHTML = '<p style="color:rgba(255,255,255,.3);text-align:center;padding:20px 0">Loading…</p>';

  const res  = await fetch('/cookstream/api/short_comment.php?short_id=' + shortId);
  const data = await res.json();
  if (data.comments && data.comments.length) {
    list.innerHTML = data.comments.map(c => `
      <div class="comment-item">
        <div class="c-avatar">${c.initial}</div>
        <div>
          <div class="c-name">${c.name}</div>
          <div class="c-text">${c.text}</div>
        </div>
      </div>`).join('');
  } else {
    list.innerHTML = '<p style="color:rgba(255,255,255,.25);text-align:center;padding:20px 0">No comments yet. Be the first!</p>';
  }
}

function closeComments() {
  document.getElementById('comments-panel').classList.remove('open');
  document.getElementById('panel-backdrop').classList.remove('open');
}

async function postComment() {
  if (!isLoggedIn) { location.href = '/cookstream/auth/login.php'; return; }
  const inp  = document.getElementById('comment-input');
  const text = inp.value.trim();
  if (!text) return;
  const res  = await fetch('/cookstream/api/short_comment.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: 'short_id=' + activeCommentShortId + '&comment=' + encodeURIComponent(text)
  });
  const data = await res.json();
  if (data.success) {
    const list = document.getElementById('comments-list');
    const html = `<div class="comment-item">
      <div class="c-avatar">${data.initial}</div>
      <div><div class="c-name">${data.name}</div><div class="c-text">${data.comment}</div></div>
    </div>`;
    list.insertAdjacentHTML('afterbegin', html);
    inp.value = '';
    showToast('💬 Comment posted!');
  }
}

// ── Share
function shareShort(shortId) {
  const url = `${location.origin}/cookstream/shorts/view.php?id=${shortId}`;
  if (navigator.share) {
    navigator.share({ title: 'CookStream Short', url });
  } else {
    navigator.clipboard.writeText(url).then(() => showToast('🔗 Link copied!'));
  }
}

// ── Toast
function showToast(msg) {
  document.querySelectorAll('.s-toast').forEach(t => t.remove());
  const t = document.createElement('div');
  t.className = 's-toast'; t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => { t.style.opacity=0; t.style.transition='opacity .4s'; setTimeout(()=>t.remove(),400); }, 2500);
}

// Keyboard nav
document.addEventListener('keydown', e => {
  if (e.key === 'ArrowDown') scrollFeed(1);
  if (e.key === 'ArrowUp')   scrollFeed(-1);
  if (e.key === 'Escape')    closeComments();
});
</script>
</body>
</html>
