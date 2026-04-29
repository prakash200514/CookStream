<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not authenticated', 'liked' => false, 'count' => 0]);
    exit;
}

$shortId = (int)($_POST['short_id'] ?? 0);
$userId  = (int)$_SESSION['user_id'];

if ($shortId <= 0) {
    echo json_encode(['error' => 'Invalid short ID', 'liked' => false, 'count' => 0]);
    exit;
}

// Check if already liked
$check = $conn->prepare("SELECT id FROM shorts_likes WHERE short_id = ? AND user_id = ?");
$check->bind_param('ii', $shortId, $userId);
$check->execute();
$existing = $check->get_result()->fetch_assoc();

if ($existing) {
    // Unlike
    $del = $conn->prepare("DELETE FROM shorts_likes WHERE short_id = ? AND user_id = ?");
    $del->bind_param('ii', $shortId, $userId);
    $del->execute();
    $liked = false;
} else {
    // Like
    $ins = $conn->prepare("INSERT INTO shorts_likes (short_id, user_id) VALUES (?, ?)");
    $ins->bind_param('ii', $shortId, $userId);
    $ins->execute();
    $liked = true;
}

// New count
$cnt = $conn->prepare("SELECT COUNT(*) AS c FROM shorts_likes WHERE short_id = ?");
$cnt->bind_param('i', $shortId);
$cnt->execute();
$count = (int)$cnt->get_result()->fetch_assoc()['c'];

echo json_encode(['liked' => $liked, 'count' => $count]);
