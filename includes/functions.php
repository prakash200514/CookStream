<?php
// ─── Note: getChannelShorts and getAllShorts added below ───────────────────────
// ─── Utility Functions ────────────────────────────────────────────────────────

function formatViews(int $n): string {
    if ($n >= 1_000_000) return round($n / 1_000_000, 1) . 'M';
    if ($n >= 1_000)     return round($n / 1_000, 1) . 'K';
    return (string)$n;
}

function timeAgo(string $datetime): string {
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);

    if ($diff->y > 0)  return $diff->y . ' year'   . ($diff->y  > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0)  return $diff->m . ' month'  . ($diff->m  > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0)  return $diff->d . ' day'    . ($diff->d  > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0)  return $diff->h . ' hour'   . ($diff->h  > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0)  return $diff->i . ' minute' . ($diff->i  > 1 ? 's' : '') . ' ago';
    return 'just now';
}

function sanitize(string $str): string {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

function generateOTP(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Save uploaded file, return relative path or false on error.
 * $type = 'video' | 'thumbnail'
 */
function saveUploadedFile(array $file, string $type): string|false {
    $allowed = [
        'video'     => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'],
        'thumbnail' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    ];

    if (!in_array($file['type'], $allowed[$type] ?? [])) return false;

    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = uniqid($type . '_', true) . '.' . $ext;
    $dir  = ($type === 'video') ? 'videos/' : 'thumbnails/';
    $dest = UPLOAD_DIR . $dir . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;
    return 'uploads/' . $dir . $name;
}

/**
 * Decode JSON array safely, return as PHP array
 */
function decodeJson(string|null $json): array {
    if (!$json) return [];
    $arr = json_decode($json, true);
    return is_array($arr) ? $arr : [];
}

function vegBadge(string $cat): string {
    if ($cat === 'veg') {
        return '<span class="badge-veg"><span class="dot"></span>Veg</span>';
    }
    return '<span class="badge-nonveg"><span class="dot"></span>Non-Veg</span>';
}

/**
 * Get all shorts for a specific channel, newest first.
 */
function getChannelShorts(mysqli $conn, int $channelId): array {
    $stmt = $conn->prepare(
        "SELECT s.*,
                (SELECT COUNT(*) FROM shorts_likes WHERE short_id = s.id) AS like_count
         FROM shorts s
         WHERE s.channel_id = ?
         ORDER BY s.created_at DESC"
    );
    $stmt->bind_param('i', $channelId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get the most recent shorts across all channels (for homepage strip / feed).
 */
function getAllShorts(mysqli $conn, int $limit = 12): array {
    $stmt = $conn->prepare(
        "SELECT s.*, c.name AS channel_name,
                (SELECT COUNT(*) FROM shorts_likes WHERE short_id = s.id) AS like_count
         FROM shorts s
         JOIN channels c ON c.id = s.channel_id
         ORDER BY s.created_at DESC
         LIMIT ?"
    );
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Save an uploaded short video file. Returns relative path or false.
 */
function saveShortFile(array $file): string|false {
    $allowed = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
    if (!in_array($file['type'], $allowed)) return false;

    $shortsDir = UPLOAD_DIR . 'shorts/';
    if (!is_dir($shortsDir)) mkdir($shortsDir, 0755, true);

    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = uniqid('short_', true) . '.' . $ext;
    $dest = $shortsDir . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;
    return 'uploads/shorts/' . $name;
}
?>
