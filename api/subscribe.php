<?php
// api/subscribe.php – toggle channel subscription
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$channelId = (int)($_POST['channel_id'] ?? 0);
$userId    = (int)$_SESSION['user_id'];

if (!$channelId) { echo json_encode(['error' => 'Invalid channel']); exit; }

$stmt = $conn->prepare("SELECT id FROM subscriptions WHERE channel_id = ? AND user_id = ?");
$stmt->bind_param('ii', $channelId, $userId);
$stmt->execute();
$exists = $stmt->get_result()->num_rows > 0;

if ($exists) {
    $del = $conn->prepare("DELETE FROM subscriptions WHERE channel_id = ? AND user_id = ?");
    $del->bind_param('ii', $channelId, $userId);
    $del->execute();
    $subscribed = false;
} else {
    $ins = $conn->prepare("INSERT IGNORE INTO subscriptions (channel_id, user_id) VALUES (?, ?)");
    $ins->bind_param('ii', $channelId, $userId);
    $ins->execute();
    $subscribed = true;
}

$cnt = $conn->prepare("SELECT COUNT(*) AS c FROM subscriptions WHERE channel_id = ?");
$cnt->bind_param('i', $channelId);
$cnt->execute();
$count = (int)$cnt->get_result()->fetch_assoc()['c'];

echo json_encode(['subscribed' => $subscribed, 'count' => $count]);
