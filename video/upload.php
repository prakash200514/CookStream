<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

// Must have a channel first
$channel = getUserChannel($conn);
if (!$channel) {
    header('Location: /cookstream/channel/create.php');
    exit;
}

// Ensure upload sub-dirs exist
$videosDir     = UPLOAD_DIR . 'videos/';
$thumbsDir     = UPLOAD_DIR . 'thumbnails/';
if (!is_dir($videosDir))  mkdir($videosDir, 0755, true);
if (!is_dir($thumbsDir))  mkdir($thumbsDir, 0755, true);

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Detect PHP silently dropping POST when file exceeds post_max_size
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postMaxBytes  = (int)ini_get('post_max_size') * 1024 * 1024;
    if ($contentLength > $postMaxBytes && empty($_POST)) {
        $fileSizeMB  = round($contentLength / 1024 / 1024, 1);
        $limitMB     = round($postMaxBytes  / 1024 / 1024, 0);
        $error = "Your file is {$fileSizeMB} MB which exceeds the server limit of {$limitMB} MB. Please use a smaller video file.";
    }

    if (!$error) {
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $category    = (($_POST['category'] ?? '') === 'non-veg') ? 'non-veg' : 'veg';
    $ingredients = trim($_POST['ingredients'] ?? '');   // stored as JSON string
    $steps       = trim($_POST['steps']       ?? '');   // stored as JSON string

    // Validate
    if (strlen($title) < 3) {
        $error = 'Title must be at least 3 characters.';
    } elseif (empty($_FILES['video']['name'])) {
        $error = 'Please select a video file to upload.';
    } elseif ($_FILES['video']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['video']['error'] === UPLOAD_ERR_FORM_SIZE) {
        $error = 'The video file is too large. Maximum allowed size is 512 MB.';
    } else {
        // Upload video
        $videoPath = saveUploadedFile($_FILES['video'], 'video');
        if (!$videoPath) {
            $error = 'Invalid video file. Allowed: MP4, WebM, OGG, MOV.';
        } else {
            // Optional thumbnail
            $thumbPath = null;
            if (!empty($_FILES['thumbnail']['name'])) {
                $t = saveUploadedFile($_FILES['thumbnail'], 'thumbnail');
                if ($t) $thumbPath = $t;
            }

            // Parse ingredients (one per line → JSON)
            $ingArr   = array_filter(array_map('trim', explode("\n", $ingredients)));
            $stepsArr = array_filter(array_map('trim', explode("\n", $steps)));
            $ingJson  = json_encode(array_values($ingArr));
            $stepsJson= json_encode(array_values($stepsArr));

            $cid  = (int)$channel['id'];
            $stmt = $conn->prepare(
                "INSERT INTO videos (channel_id, title, description, ingredients, steps, category, video_path, thumbnail_path)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'isssssss',
                $cid, $title, $description, $ingJson, $stepsJson,
                $category, $videoPath, $thumbPath
            );

            if ($stmt->execute()) {
                $newId = $conn->insert_id;
                header("Location: /cookstream/video/watch.php?id=$newId&uploaded=1");
                exit;
            } else {
                $error = 'Database error: ' . $conn->error;
            }
        }
    }
    } // end if(!$error) for size check
}

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Upload Video – CookStream</title>
  <meta name="description" content="Upload your cooking video to CookStream and share your recipe with food lovers.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/cookstream/assets/css/style.css">
  <style>
    body { font-family:'Outfit',sans-serif; }

    .upload-page {
      min-height:100vh;
      padding: 40px 20px 80px;
      background: radial-gradient(ellipse at 10% 60%, hsla(25,100%,60%,.12) 0%,transparent 55%),
                  radial-gradient(ellipse at 85% 15%, hsla(260,80%,60%,.10) 0%,transparent 55%),
                  var(--bg-dark,#0f0f0f);
    }

    .upload-wrap {
      max-width: 820px;
      margin: 0 auto;
    }

    .page-heading {
      font-size:1.9rem;
      font-weight:800;
      color:#fff;
      margin:0 0 6px;
    }
    .page-sub {
      color:rgba(255,255,255,.4);
      font-size:.9rem;
      margin:0 0 36px;
    }

    /* Two-col layout */
    .upload-grid {
      display:grid;
      grid-template-columns:1fr 340px;
      gap:24px;
    }
    @media(max-width:720px){ .upload-grid{grid-template-columns:1fr;} }

    /* Cards */
    .upload-card {
      background:rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.08);
      border-radius:20px;
      padding:28px;
    }

    .card-title {
      font-size:.78rem;
      font-weight:700;
      text-transform:uppercase;
      letter-spacing:.07em;
      color:rgba(255,255,255,.4);
      margin:0 0 18px;
    }

    /* Form controls */
    .form-group { margin-bottom:20px; }
    .form-group label {
      display:block;
      font-size:.82rem;
      font-weight:600;
      color:rgba(255,255,255,.55);
      margin-bottom:7px;
      letter-spacing:.04em;
    }

    .form-control {
      width:100%;
      padding:12px 15px;
      background:rgba(255,255,255,.06);
      border:1px solid rgba(255,255,255,.1);
      border-radius:12px;
      color:#fff;
      font-family:'Outfit',sans-serif;
      font-size:.95rem;
      outline:none;
      transition:border-color .2s,box-shadow .2s;
      box-sizing:border-box;
    }
    .form-control:focus {
      border-color:hsl(25,100%,60%);
      box-shadow:0 0 0 3px hsla(25,100%,60%,.18);
    }
    textarea.form-control { resize:vertical; min-height:90px; }

    /* Category toggle */
    .category-toggle {
      display:flex;
      gap:10px;
    }
    .cat-btn {
      flex:1;
      padding:11px;
      border:2px solid rgba(255,255,255,.1);
      border-radius:12px;
      background:transparent;
      color:rgba(255,255,255,.5);
      font-family:'Outfit',sans-serif;
      font-size:.9rem;
      font-weight:600;
      cursor:pointer;
      transition:all .2s;
      text-align:center;
    }
    .cat-btn.active-veg     { border-color:#22c55e; background:rgba(34,197,94,.12); color:#86efac; }
    .cat-btn.active-nonveg  { border-color:#ef4444; background:rgba(239,68,68,.12); color:#fca5a5; }
    #category-input { display:none; }

    /* Drop zone */
    .drop-zone {
      border:2px dashed rgba(255,255,255,.15);
      border-radius:16px;
      padding:36px 20px;
      text-align:center;
      cursor:pointer;
      transition:border-color .2s,background .2s;
      position:relative;
      margin-bottom:20px;
    }
    .drop-zone:hover,
    .drop-zone.drag-over {
      border-color:hsl(25,100%,60%);
      background:rgba(255,140,0,.05);
    }
    .drop-zone input[type=file] {
      position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;
    }
    .drop-icon { font-size:2.8rem; margin-bottom:10px; }
    .drop-zone p { color:rgba(255,255,255,.38); font-size:.85rem; margin:4px 0; }
    .drop-zone span { color:hsl(25,100%,60%); font-weight:600; }

    #video-info {
      background:rgba(255,255,255,.05);
      border-radius:10px;
      padding:12px 14px;
      font-size:.85rem;
      color:rgba(255,255,255,.6);
      display:none;
      margin-top:-10px;
      margin-bottom:20px;
    }
    .progress-bar-wrap {
      height:4px;
      background:rgba(255,255,255,.1);
      border-radius:99px;
      margin-top:8px;
      overflow:hidden;
      display:none;
    }
    .progress-bar {
      height:100%;
      width:0%;
      background:linear-gradient(90deg,hsl(25,100%,55%),hsl(10,90%,55%));
      border-radius:99px;
      transition:width .3s;
    }

    /* Thumbnail drop */
    .thumb-drop {
      border:2px dashed rgba(255,255,255,.1);
      border-radius:14px;
      padding:22px;
      text-align:center;
      cursor:pointer;
      transition:border-color .2s,background .2s;
      position:relative;
      margin-bottom:6px;
    }
    .thumb-drop:hover { border-color:hsl(25,100%,60%); background:rgba(255,140,0,.04); }
    .thumb-drop input[type=file] {
      position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;
    }
    .thumb-drop .upload-icon { font-size:1.8rem; margin-bottom:6px; }
    .thumb-drop p { color:rgba(255,255,255,.35); font-size:.8rem; margin:0; }

    #thumb-preview {
      width:100%;
      border-radius:10px;
      max-height:130px;
      object-fit:cover;
      display:none;
      margin-top:12px;
    }

    /* Submit */
    .btn-upload {
      width:100%;
      padding:15px;
      background:linear-gradient(135deg,hsl(25,100%,55%),hsl(10,90%,55%));
      color:#fff;
      border:none;
      border-radius:13px;
      font-family:'Outfit',sans-serif;
      font-size:1rem;
      font-weight:700;
      cursor:pointer;
      letter-spacing:.03em;
      transition:transform .15s,box-shadow .15s,opacity .15s;
      box-shadow:0 8px 24px hsla(25,100%,50%,.35);
    }
    .btn-upload:hover  { transform:translateY(-2px); box-shadow:0 12px 32px hsla(25,100%,50%,.45); }
    .btn-upload:active { opacity:.9; transform:translateY(0); }
    .btn-upload:disabled { opacity:.55; cursor:not-allowed; transform:none; }

    /* Alert */
    .alert {
      padding:12px 16px;
      border-radius:10px;
      font-size:.88rem;
      margin-bottom:24px;
      display:flex;
      align-items:center;
      gap:8px;
    }
    .alert-error { background:rgba(239,68,68,.15); border:1px solid rgba(239,68,68,.35); color:#fca5a5; }

    /* Char counter */
    .char-counter { text-align:right; font-size:.72rem; color:rgba(255,255,255,.25); margin-top:3px; }

    /* Ingredients / Steps hint */
    .field-hint { font-size:.78rem; color:rgba(255,255,255,.28); margin-top:5px; }
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

<div class="upload-page">
  <div class="upload-wrap">
    <h1 class="page-heading">⬆ Upload a Video</h1>
    <p class="page-sub">Share your recipe with the CookStream community — channel: <strong style="color:#fff"><?= sanitize($channel['name']) ?></strong></p>

    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= sanitize($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="upload-form">
      <div class="upload-grid">

        <!-- Left column -->
        <div>
          <!-- Video file -->
          <div class="upload-card" style="margin-bottom:20px">
            <p class="card-title">📹 Video File *</p>
            <div class="drop-zone" id="video-drop">
              <input type="file" name="video" id="video-input" accept="video/*" required>
              <div class="drop-icon">🎬</div>
              <p><span>Click to choose</span> or drag & drop your video</p>
              <p>MP4 · WebM · MOV · OGG &nbsp;|&nbsp; Max 500 MB</p>
            </div>
            <div id="video-info"></div>
          </div>

          <!-- Details -->
          <div class="upload-card">
            <p class="card-title">📝 Video Details</p>

            <div class="form-group">
              <label for="title-input">Title *</label>
              <input type="text" id="title-input" name="title" class="form-control"
                     maxlength="200" placeholder="e.g. Creamy Butter Chicken in 30 min"
                     value="<?= sanitize($_POST['title'] ?? '') ?>" required>
              <div class="char-counter"><span id="title-count">0</span>/200</div>
            </div>

            <div class="form-group">
              <label for="desc-input">Description</label>
              <textarea id="desc-input" name="description" class="form-control"
                        maxlength="1000" placeholder="What's this recipe about?"><?= sanitize($_POST['description'] ?? '') ?></textarea>
              <div class="char-counter"><span id="desc-count">0</span>/1000</div>
            </div>

            <div class="form-group">
              <label>Category *</label>
              <div class="category-toggle">
                <button type="button" class="cat-btn active-veg" id="btn-veg"   onclick="setCategory('veg')">🥦 Vegetarian</button>
                <button type="button" class="cat-btn"            id="btn-nonveg" onclick="setCategory('non-veg')">🍗 Non-Vegetarian</button>
              </div>
              <input type="hidden" name="category" id="category-input" value="veg">
            </div>

            <div class="form-group">
              <label for="ingredients-input">Ingredients (one per line)</label>
              <textarea id="ingredients-input" name="ingredients" class="form-control"
                        style="min-height:110px"
                        placeholder="2 cups basmati rice&#10;1 tbsp butter&#10;Salt to taste"><?= sanitize($_POST['ingredients'] ?? '') ?></textarea>
              <div class="field-hint">Each line becomes a separate ingredient item.</div>
            </div>

            <div class="form-group">
              <label for="steps-input">Cooking Steps (one per line)</label>
              <textarea id="steps-input" name="steps" class="form-control"
                        style="min-height:130px"
                        placeholder="Rinse and soak the rice for 20 minutes&#10;Heat butter in a pan&#10;Add spices and cook for 2 minutes"><?= sanitize($_POST['steps'] ?? '') ?></textarea>
              <div class="field-hint">Each line becomes a numbered step.</div>
            </div>
          </div>
        </div>

        <!-- Right column -->
        <div>
          <div class="upload-card" style="margin-bottom:20px">
            <p class="card-title">🖼 Thumbnail (optional)</p>
            <div class="thumb-drop" id="thumb-drop">
              <input type="file" name="thumbnail" id="thumb-input" accept="image/*">
              <div class="upload-icon">📸</div>
              <p><span style="color:hsl(25,100%,60%);font-weight:600">Click to upload</span></p>
              <p>JPG · PNG · WebP &nbsp;|&nbsp; Max 5 MB</p>
              <img id="thumb-preview" src="" alt="Thumbnail preview">
            </div>
            <p style="font-size:.75rem;color:rgba(255,255,255,.25);margin:0">
              Recommended: 1280×720 px
            </p>
          </div>

          <div class="upload-card">
            <p class="card-title">📋 Upload Summary</p>
            <div id="summary-box" style="font-size:.85rem;color:rgba(255,255,255,.4);line-height:1.7">
              <div>🎬 Video: <span id="s-video" style="color:#fff">—</span></div>
              <div>📸 Thumb: <span id="s-thumb" style="color:#fff">—</span></div>
              <div>🏷 Category: <span id="s-cat" style="color:#86efac">🥦 Veg</span></div>
            </div>
            <div class="progress-bar-wrap" id="progress-wrap">
              <div class="progress-bar" id="progress-bar"></div>
            </div>
            <button type="submit" class="btn-upload" id="submit-btn" style="margin-top:22px">
              🚀 Publish Video
            </button>
          </div>
        </div>

      </div>
    </form>
  </div>
</div>

<script>
  // ── Category toggle
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

  // ── Char counters
  function bindCounter(inputId, countId) {
    const el = document.getElementById(inputId);
    const cn = document.getElementById(countId);
    if (!el || !cn) return;
    el.addEventListener('input', () => { cn.textContent = el.value.length; });
    cn.textContent = el.value.length;
  }
  bindCounter('title-input', 'title-count');
  bindCounter('desc-input',  'desc-count');

  // ── Video file info
  document.getElementById('video-input').addEventListener('change', function () {
    const f = this.files[0];
    if (!f) return;
    const mb = (f.size / 1024 / 1024).toFixed(1);
    document.getElementById('video-info').style.display = 'block';
    document.getElementById('video-info').innerHTML =
      `✅ <strong style="color:#fff">${f.name}</strong> &nbsp;(${mb} MB)`;
    document.getElementById('s-video').textContent = f.name;
  });

  // ── Thumbnail preview
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
    document.getElementById('s-thumb').textContent = f.name;
  });

  // ── Drag-over style
  const dropZone = document.getElementById('video-drop');
  ['dragenter','dragover'].forEach(ev =>
    dropZone.addEventListener(ev, e => { e.preventDefault(); dropZone.classList.add('drag-over'); })
  );
  ['dragleave','drop'].forEach(ev =>
    dropZone.addEventListener(ev, () => dropZone.classList.remove('drag-over'))
  );

  // ── Submit: fake progress animation
  document.getElementById('upload-form').addEventListener('submit', function () {
    const btn  = document.getElementById('submit-btn');
    const wrap = document.getElementById('progress-wrap');
    const bar  = document.getElementById('progress-bar');
    btn.disabled = true;
    btn.textContent = '⏳ Uploading…';
    wrap.style.display = 'block';
    let w = 0;
    const timer = setInterval(() => {
      w = Math.min(w + Math.random() * 12, 90);
      bar.style.width = w + '%';
    }, 400);
  });
</script>
</body>
</html>
