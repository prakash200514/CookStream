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

// Ensure shorts upload dir exists
$shortsDir = UPLOAD_DIR . 'shorts/';
$thumbsDir = UPLOAD_DIR . 'thumbnails/';
if (!is_dir($shortsDir)) mkdir($shortsDir, 0755, true);
if (!is_dir($thumbsDir)) mkdir($thumbsDir, 0755, true);

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postMaxBytes  = (int)ini_get('post_max_size') * 1024 * 1024;
    if ($contentLength > $postMaxBytes && empty($_POST)) {
        $fileSizeMB = round($contentLength / 1024 / 1024, 1);
        $limitMB    = round($postMaxBytes / 1024 / 1024, 0);
        $error = "Your file ({$fileSizeMB} MB) exceeds the server limit of {$limitMB} MB.";
    }

    if (!$error) {
        $title       = trim($_POST['title']       ?? '');
        $description = trim($_POST['description'] ?? '');
        $category    = (($_POST['category'] ?? '') === 'non-veg') ? 'non-veg' : 'veg';

        if (strlen($title) < 3) {
            $error = 'Title must be at least 3 characters.';
        } elseif (empty($_FILES['short_video']['name'])) {
            $error = 'Please select a video file.';
        } elseif ($_FILES['short_video']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['short_video']['error'] === UPLOAD_ERR_FORM_SIZE) {
            $error = 'Video is too large. Maximum allowed is 100 MB.';
        } elseif ($_FILES['short_video']['size'] > 100 * 1024 * 1024) {
            $error = 'Short videos must be under 100 MB.';
        } else {
            $videoPath = saveShortFile($_FILES['short_video']);
            if (!$videoPath) {
                $error = 'Invalid video file. Allowed: MP4, WebM, MOV, OGG.';
            } else {
                // Optional thumbnail
                $thumbPath = null;
                if (!empty($_FILES['thumbnail']['name'])) {
                    $t = saveUploadedFile($_FILES['thumbnail'], 'thumbnail');
                    if ($t) $thumbPath = $t;
                }

                $cid  = (int)$channel['id'];
                $stmt = $conn->prepare(
                    "INSERT INTO shorts (channel_id, title, description, category, video_path, thumbnail_path)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('isssss', $cid, $title, $description, $category, $videoPath, $thumbPath);

                if ($stmt->execute()) {
                    $newId = $conn->insert_id;
                    header("Location: /cookstream/shorts/view.php?id=$newId&uploaded=1");
                    exit;
                } else {
                    $error = 'Database error: ' . $conn->error;
                }
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
  <title>Upload a Short – CookStream</title>
  <meta name="description" content="Upload a short cooking video to CookStream Shorts and reach food lovers instantly.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/cookstream/assets/css/style.css">
  <style>
    body { font-family:'Outfit',sans-serif; }

    .shorts-upload-page {
      min-height: 100vh;
      padding: 40px 20px 80px;
      background:
        radial-gradient(ellipse at 15% 50%, hsla(280,90%,60%,.13) 0%, transparent 55%),
        radial-gradient(ellipse at 85% 10%, hsla(340,90%,60%,.11) 0%, transparent 55%),
        var(--bg-dark,#0f0f0f);
    }

    .upload-wrap { max-width: 760px; margin: 0 auto; }

    .page-heading {
      font-size: 2rem; font-weight: 800; color: #fff; margin: 0 0 6px;
      background: linear-gradient(135deg, #a855f7, #ec4899);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    }
    .page-sub { color: rgba(255,255,255,.4); font-size: .9rem; margin: 0 0 36px; }

    /* two-col */
    .upload-grid { display: grid; grid-template-columns: 1fr 300px; gap: 24px; }
    @media(max-width:680px) { .upload-grid { grid-template-columns: 1fr; } }

    .upload-card {
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 20px;
      padding: 26px;
    }
    .card-title {
      font-size: .78rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .07em; color: rgba(255,255,255,.4); margin: 0 0 18px;
    }

    /* Vertical preview badge */
    .vertical-badge {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(168,85,247,.18); border: 1px solid rgba(168,85,247,.35);
      color: #d8b4fe; padding: 4px 10px; border-radius: 20px;
      font-size: .78rem; font-weight: 600; margin-bottom: 16px;
    }

    /* Drop zone */
    .drop-zone {
      border: 2px dashed rgba(168,85,247,.3);
      border-radius: 16px; padding: 36px 20px;
      text-align: center; cursor: pointer;
      transition: border-color .2s, background .2s;
      position: relative; margin-bottom: 16px;
    }
    .drop-zone:hover, .drop-zone.drag-over {
      border-color: #a855f7;
      background: rgba(168,85,247,.06);
    }
    .drop-zone input[type=file] {
      position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
    }
    .drop-icon { font-size: 2.8rem; margin-bottom: 10px; }
    .drop-zone p { color: rgba(255,255,255,.38); font-size: .85rem; margin: 4px 0; }
    .drop-zone span { color: #a855f7; font-weight: 600; }

    #video-info {
      background: rgba(255,255,255,.05); border-radius: 10px;
      padding: 12px 14px; font-size: .85rem; color: rgba(255,255,255,.6);
      display: none; margin-top: -6px; margin-bottom: 16px;
    }
    .duration-warning {
      background: rgba(251,191,36,.12); border: 1px solid rgba(251,191,36,.3);
      color: #fcd34d; border-radius: 8px; padding: 8px 12px;
      font-size: .8rem; margin-top: 6px; display: none;
    }

    /* Form */
    .form-group { margin-bottom: 20px; }
    .form-group label {
      display: block; font-size: .82rem; font-weight: 600;
      color: rgba(255,255,255,.55); margin-bottom: 7px; letter-spacing: .04em;
    }
    .form-control {
      width: 100%; padding: 12px 15px;
      background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
      border-radius: 12px; color: #fff; font-family: 'Outfit',sans-serif;
      font-size: .95rem; outline: none;
      transition: border-color .2s, box-shadow .2s; box-sizing: border-box;
    }
    .form-control:focus { border-color: #a855f7; box-shadow: 0 0 0 3px rgba(168,85,247,.2); }
    textarea.form-control { resize: vertical; min-height: 80px; }
    .char-counter { text-align: right; font-size: .72rem; color: rgba(255,255,255,.25); margin-top: 3px; }

    /* Category */
    .category-toggle { display: flex; gap: 10px; }
    .cat-btn {
      flex: 1; padding: 10px; border: 2px solid rgba(255,255,255,.1);
      border-radius: 12px; background: transparent; color: rgba(255,255,255,.5);
      font-family: 'Outfit',sans-serif; font-size: .9rem; font-weight: 600;
      cursor: pointer; transition: all .2s; text-align: center;
    }
    .cat-btn.active-veg    { border-color: #22c55e; background: rgba(34,197,94,.12); color: #86efac; }
    .cat-btn.active-nonveg { border-color: #ef4444; background: rgba(239,68,68,.12); color: #fca5a5; }

    /* Vertical phone preview */
    .phone-preview {
      background: #111; border: 2px solid rgba(255,255,255,.1);
      border-radius: 24px; overflow: hidden;
      aspect-ratio: 9/16; position: relative; max-height: 320px;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 16px;
    }
    .phone-preview video {
      width: 100%; height: 100%; object-fit: cover; display: none;
    }
    .phone-preview .preview-placeholder {
      text-align: center; color: rgba(255,255,255,.3);
    }
    .phone-preview .preview-placeholder span { font-size: 2.5rem; display: block; }

    /* Thumb */
    .thumb-drop {
      border: 2px dashed rgba(255,255,255,.1); border-radius: 14px;
      padding: 18px; text-align: center; cursor: pointer;
      transition: border-color .2s; position: relative; margin-bottom: 12px;
    }
    .thumb-drop:hover { border-color: #a855f7; }
    .thumb-drop input[type=file] {
      position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
    }
    .thumb-drop p { color: rgba(255,255,255,.35); font-size: .78rem; margin: 4px 0; }
    #thumb-preview {
      width: 100%; border-radius: 10px; max-height: 100px;
      object-fit: cover; display: none; margin-top: 8px;
    }

    /* Submit */
    .btn-upload {
      width: 100%; padding: 15px;
      background: linear-gradient(135deg, #a855f7, #ec4899);
      color: #fff; border: none; border-radius: 13px;
      font-family: 'Outfit',sans-serif; font-size: 1rem; font-weight: 700;
      cursor: pointer; letter-spacing: .03em;
      transition: transform .15s, box-shadow .15s, opacity .15s;
      box-shadow: 0 8px 24px rgba(168,85,247,.4); margin-top: 20px;
    }
    .btn-upload:hover  { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(168,85,247,.5); }
    .btn-upload:active { opacity: .9; transform: translateY(0); }
    .btn-upload:disabled { opacity: .5; cursor: not-allowed; transform: none; }

    /* Alert */
    .alert { padding: 12px 16px; border-radius: 10px; font-size: .88rem; margin-bottom: 24px; display: flex; align-items: center; gap: 8px; }
    .alert-error { background: rgba(239,68,68,.15); border: 1px solid rgba(239,68,68,.35); color: #fca5a5; }

    /* Summary */
    .summary-row { font-size: .82rem; color: rgba(255,255,255,.4); line-height: 1.9; }
    .summary-row strong { color: #fff; }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
  <a class="navbar-brand" href="/cookstream/">
    <span class="logo-icon">🍳</span> CookStream
  </a>
  <div class="nav-actions">
    <a href="/cookstream/channel/dashboard.php" class="btn btn-outline">📺 My Channel</a>
    <a href="/cookstream/auth/logout.php" class="btn btn-ghost">Sign Out</a>
    <div class="avatar" title="<?= sanitize($user['name']) ?>"><?= strtoupper($user['name'][0]) ?></div>
  </div>
</nav>

<div class="shorts-upload-page">
  <div class="upload-wrap">
    <h1 class="page-heading">📱 Upload a Short</h1>
    <p class="page-sub">Vertical cooking clips · Max 60 seconds · Channel: <strong style="color:#fff"><?= sanitize($channel['name']) ?></strong></p>

    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= sanitize($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="short-upload-form">
      <div class="upload-grid">

        <!-- Left -->
        <div>
          <div class="upload-card" style="margin-bottom:20px">
            <p class="card-title">📹 Short Video *</p>
            <div class="vertical-badge">📱 Vertical (9:16 recommended)</div>
            <div class="drop-zone" id="video-drop">
              <input type="file" name="short_video" id="video-input" accept="video/*" required>
              <div class="drop-icon">🎬</div>
              <p><span>Click to choose</span> or drag & drop</p>
              <p>MP4 · WebM · MOV · Max 100 MB · Max 60s</p>
            </div>
            <div id="video-info"></div>
            <div class="duration-warning" id="duration-warn">
              ⚠️ Your video is longer than 60 seconds. Shorts work best under 60s!
            </div>
          </div>

          <div class="upload-card">
            <p class="card-title">📝 Details</p>

            <div class="form-group">
              <label for="title-input">Title *</label>
              <input type="text" id="title-input" name="title" class="form-control"
                     maxlength="200" placeholder="e.g. 30-Second Butter Garlic Naan"
                     value="<?= sanitize($_POST['title'] ?? '') ?>" required>
              <div class="char-counter"><span id="title-count">0</span>/200</div>
            </div>

            <div class="form-group">
              <label for="desc-input">Description</label>
              <textarea id="desc-input" name="description" class="form-control"
                        maxlength="500" placeholder="Quick tip or recipe summary…"><?= sanitize($_POST['description'] ?? '') ?></textarea>
              <div class="char-counter"><span id="desc-count">0</span>/500</div>
            </div>

            <div class="form-group">
              <label>Category *</label>
              <div class="category-toggle">
                <button type="button" class="cat-btn active-veg" id="btn-veg"    onclick="setCategory('veg')">🥦 Veg</button>
                <button type="button" class="cat-btn"            id="btn-nonveg" onclick="setCategory('non-veg')">🍗 Non-Veg</button>
              </div>
              <input type="hidden" name="category" id="category-input" value="veg">
            </div>
          </div>
        </div>

        <!-- Right -->
        <div>
          <div class="upload-card" style="margin-bottom:20px">
            <p class="card-title">📱 Preview</p>
            <div class="phone-preview" id="phone-preview">
              <video id="preview-video" playsinline muted loop controls></video>
              <div class="preview-placeholder" id="preview-placeholder">
                <span>📱</span>
                <p style="font-size:.82rem;margin:8px 0 0">Video preview here</p>
              </div>
            </div>
          </div>

          <div class="upload-card" style="margin-bottom:20px">
            <p class="card-title">🖼 Thumbnail (optional)</p>
            <div class="thumb-drop" id="thumb-drop">
              <input type="file" name="thumbnail" id="thumb-input" accept="image/*">
              <div style="font-size:1.6rem">📸</div>
              <p><span style="color:#a855f7;font-weight:600">Click to upload</span></p>
              <p>JPG · PNG · WebP</p>
              <img id="thumb-preview" src="" alt="Thumbnail">
            </div>
          </div>

          <div class="upload-card">
            <p class="card-title">📋 Summary</p>
            <div class="summary-row">
              <div>🎬 File: <strong id="s-video">—</strong></div>
              <div>🏷 Type: <span id="s-cat" style="color:#86efac">🥦 Veg</span></div>
              <div>⏱ Duration: <strong id="s-dur">—</strong></div>
            </div>
            <button type="submit" class="btn-upload" id="submit-btn">
              🚀 Publish Short
            </button>
          </div>
        </div>

      </div>
    </form>
  </div>
</div>

<script>
  // Category toggle
  function setCategory(val) {
    document.getElementById('category-input').value = val;
    const bv = document.getElementById('btn-veg');
    const bn = document.getElementById('btn-nonveg');
    const sc = document.getElementById('s-cat');
    bv.className = 'cat-btn' + (val === 'veg'     ? ' active-veg'    : '');
    bn.className = 'cat-btn' + (val === 'non-veg' ? ' active-nonveg' : '');
    sc.innerHTML = val === 'veg'
      ? '<span style="color:#86efac">🥦 Veg</span>'
      : '<span style="color:#fca5a5">🍗 Non-Veg</span>';
  }

  // Char counters
  function bindCounter(inputId, countId) {
    const el = document.getElementById(inputId);
    const cn = document.getElementById(countId);
    if (!el || !cn) return;
    el.addEventListener('input', () => { cn.textContent = el.value.length; });
    cn.textContent = el.value.length;
  }
  bindCounter('title-input', 'title-count');
  bindCounter('desc-input',  'desc-count');

  // Video file selection
  document.getElementById('video-input').addEventListener('change', function () {
    const f = this.files[0];
    if (!f) return;
    const mb = (f.size / 1024 / 1024).toFixed(1);
    document.getElementById('video-info').style.display = 'block';
    document.getElementById('video-info').innerHTML =
      `✅ <strong style="color:#fff">${f.name}</strong> &nbsp;(${mb} MB)`;
    document.getElementById('s-video').textContent = f.name;

    // Preview
    const url = URL.createObjectURL(f);
    const vid = document.getElementById('preview-video');
    vid.src = url;
    vid.style.display = 'block';
    document.getElementById('preview-placeholder').style.display = 'none';

    // Duration check
    vid.addEventListener('loadedmetadata', function () {
      const dur = Math.round(vid.duration);
      document.getElementById('s-dur').textContent = dur + 's';
      document.getElementById('duration-warn').style.display =
        dur > 60 ? 'block' : 'none';
    }, { once: true });
  });

  // Thumbnail preview
  document.getElementById('thumb-input').addEventListener('change', function () {
    const f = this.files[0];
    if (!f) return;
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.getElementById('thumb-preview');
      img.src = e.target.result;
      img.style.display = 'block';
    };
    reader.readAsDataURL(f);
  });

  // Drag over
  const dropZone = document.getElementById('video-drop');
  ['dragenter','dragover'].forEach(ev =>
    dropZone.addEventListener(ev, e => { e.preventDefault(); dropZone.classList.add('drag-over'); })
  );
  ['dragleave','drop'].forEach(ev =>
    dropZone.addEventListener(ev, () => dropZone.classList.remove('drag-over'))
  );

  // Submit loading
  document.getElementById('short-upload-form').addEventListener('submit', function () {
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.textContent = '⏳ Uploading…';
  });
</script>
</body>
</html>
