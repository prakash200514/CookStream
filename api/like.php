<?php
// api/like.php – toggle like on a video
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$videoId = (int)($_POST['video_id'] ?? 0);
$userId  = (int)$_SESSION['user_id'];

if (!$videoId) { echo json_encode(['error' => 'Invalid video']); exit; }

// Check if already liked
$stmt = $conn->prepare("SELECT id FROM likes WHERE video_id = ? AND user_id = ?");
$stmt->bind_param('ii', $videoId, $userId);
$stmt->execute();
$exists = $stmt->get_result()->num_rows > 0;

if ($exists) {
    $del = $conn->prepare("DELETE FROM likes WHERE video_id = ? AND user_id = ?");
    $del->bind_param('ii', $videoId, $userId);
    $del->execute();
    $liked = false;
} else {
    $ins = $conn->prepare("INSERT IGNORE INTO likes (video_id, user_id) VALUES (?, ?)");
    $ins->bind_param('ii', $videoId, $userId);
    $ins->execute();
    $liked = true;
}

$cnt = $conn->prepare("SELECT COUNT(*) AS c FROM likes WHERE video_id = ?");
$cnt->bind_param('i', $videoId);
$cnt->execute();
$count = (int)$cnt->get_result()->fetch_assoc()['c'];

echo json_encode(['liked' => $liked, 'count' => $count]);
