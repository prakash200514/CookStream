<?php
// api/comment.php – post a comment on a video
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$videoId = (int)($_POST['video_id'] ?? 0);
$comment = trim($_POST['comment'] ?? '');
$userId  = (int)$_SESSION['user_id'];

if (!$videoId || strlen($comment) < 1) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$comment = substr($comment, 0, 1000); // max length

$stmt = $conn->prepare("INSERT INTO comments (video_id, user_id, comment) VALUES (?, ?, ?)");
$stmt->bind_param('iis', $videoId, $userId, $comment);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'name'    => sanitize($_SESSION['user_name']),
        'initial' => strtoupper($_SESSION['user_name'][0]),
        'comment' => sanitize($comment),
    ]);
} else {
    echo json_encode(['error' => 'DB error']);
}
