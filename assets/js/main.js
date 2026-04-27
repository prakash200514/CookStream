// ── CookStream Main JS ────────────────────────────────────────────────────────

// ── Toast ──────────────────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
  let t = document.getElementById('cs-toast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'cs-toast';
    t.className = 'toast';
    document.body.appendChild(t);
  }
  t.textContent = (type === 'success' ? '✅ ' : '❌ ') + msg;
  t.className = `toast ${type} show`;
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove('show'), 3000);
}

// ── Live Search ────────────────────────────────────────────────────────────
const searchInput = document.getElementById('search-input');
const searchResults = document.getElementById('search-results');
let searchTimer;
if (searchInput) {
  searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    const q = searchInput.value.trim();
    if (!searchResults) return;
    if (q.length < 2) { searchResults.style.display = 'none'; return; }
    searchTimer = setTimeout(async () => {
      const res = await fetch(`/cookstream/api/search.php?q=${encodeURIComponent(q)}`);
      const data = await res.json();
      if (!data.length) { searchResults.style.display = 'none'; return; }
      searchResults.innerHTML = data.map(v =>
        `<a href="/cookstream/video/watch.php?id=${v.id}">🎬 ${v.title}</a>`
      ).join('');
      searchResults.style.display = 'block';
    }, 280);
  });
  document.addEventListener('click', e => {
    if (!searchInput.contains(e.target)) searchResults.style.display = 'none';
  });
  searchInput.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      const q = searchInput.value.trim();
      if (q) window.location.href = `/cookstream/?q=${encodeURIComponent(q)}`;
    }
  });
}

// ── Filter buttons ─────────────────────────────────────────────────────────
document.querySelectorAll('.filter-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const filter = btn.dataset.filter;
    const url = new URL(window.location.href);
    if (filter === 'all') url.searchParams.delete('cat');
    else url.searchParams.set('cat', filter);
    window.location.href = url.toString();
  });
});

// ── Like Toggle ────────────────────────────────────────────────────────────
const likeBtn = document.getElementById('like-btn');
if (likeBtn) {
  likeBtn.addEventListener('click', async () => {
    const vid = likeBtn.dataset.video;
    const res = await fetch('/cookstream/api/like.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `video_id=${vid}`
    });
    const data = await res.json();
    if (data.error) { showToast(data.error, 'error'); return; }
    likeBtn.classList.toggle('liked', data.liked);
    document.getElementById('like-count').textContent = data.count;
    showToast(data.liked ? 'Added to liked videos' : 'Removed from liked videos');
  });
}

// ── Share ─────────────────────────────────────────────────────────────────
const shareBtn = document.getElementById('share-btn');
if (shareBtn) {
  shareBtn.addEventListener('click', () => {
    navigator.clipboard.writeText(window.location.href)
      .then(() => showToast('Link copied to clipboard!'))
      .catch(() => showToast('Copy failed', 'error'));
  });
}

// ── Comment Submit ─────────────────────────────────────────────────────────
const commentForm = document.getElementById('comment-form');
if (commentForm) {
  commentForm.addEventListener('submit', async e => {
    e.preventDefault();
    const ta = document.getElementById('comment-text');
    const comment = ta.value.trim();
    const vid = commentForm.dataset.video;
    if (!comment) return;
    const res = await fetch('/cookstream/api/comment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `video_id=${vid}&comment=${encodeURIComponent(comment)}`
    });
    const data = await res.json();
    if (data.error) { showToast(data.error, 'error'); return; }
    ta.value = '';
    const list = document.getElementById('comments-list');
    list.insertAdjacentHTML('afterbegin', renderComment(data));
    showToast('Comment posted!');
  });
}
function renderComment(c) {
  return `<div class="comment-item">
    <div class="avatar">${c.name[0].toUpperCase()}</div>
    <div class="comment-body">
      <span class="author">${c.name}</span><span class="time">just now</span>
      <p>${c.comment}</p>
    </div>
  </div>`;
}

// ── Subscribe Toggle ───────────────────────────────────────────────────────
const subBtn = document.getElementById('subscribe-btn');
if (subBtn) {
  subBtn.addEventListener('click', async () => {
    const cid = subBtn.dataset.channel;
    const res = await fetch('/cookstream/api/subscribe.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `channel_id=${cid}`
    });
    const data = await res.json();
    if (data.error) { showToast(data.error, 'error'); return; }
    subBtn.textContent = data.subscribed ? '🔔 Subscribed' : '🔔 Subscribe';
    subBtn.classList.toggle('btn-primary', data.subscribed);
    subBtn.classList.toggle('btn-outline', !data.subscribed);
    document.getElementById('sub-count').textContent = data.count;
  });
}

// ── OTP Input Auto-advance ─────────────────────────────────────────────────
const otpInputs = document.querySelectorAll('.otp-inputs input');
otpInputs.forEach((inp, i) => {
  inp.addEventListener('input', () => {
    if (inp.value.length === 1 && i < otpInputs.length - 1) otpInputs[i + 1].focus();
    collateOTP();
  });
  inp.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !inp.value && i > 0) otpInputs[i - 1].focus();
  });
  inp.addEventListener('paste', e => {
    const text = e.clipboardData.getData('text').replace(/\D/g, '');
    otpInputs.forEach((el, j) => { el.value = text[j] || ''; });
    collateOTP();
    e.preventDefault();
  });
});
function collateOTP() {
  const h = document.getElementById('otp-hidden');
  if (h) h.value = [...otpInputs].map(i => i.value).join('');
}

// ── Dynamic Ingredient/Step rows ──────────────────────────────────────────
function addRow(listId, placeholder) {
  const list = document.getElementById(listId);
  const row = document.createElement('div');
  row.className = 'item-row';
  row.innerHTML = `<input type="text" placeholder="${placeholder}" name="${listId}[]">
    <button type="button" class="remove-row" onclick="this.parentElement.remove()">✕</button>`;
  list.appendChild(row);
}

// ── Drag-over highlight ────────────────────────────────────────────────────
document.querySelectorAll('.drop-zone').forEach(zone => {
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
  zone.addEventListener('drop', () => zone.classList.remove('dragover'));
  const input = zone.querySelector('input[type=file]');
  if (input) {
    input.addEventListener('change', () => {
      const f = input.files[0];
      if (f) zone.querySelector('p').innerHTML = `<strong>${f.name}</strong> selected`;
    });
  }
});
