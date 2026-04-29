<?php
// api/short_comment.php – GET list or POST new comment for a short
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$shortId = (int)($_GET['short_id'] ?? $_POST['short_id'] ?? 0);
if (!$shortId) { echo json_encode(['error' => 'Invalid short']); exit; }

// ── GET: fetch comments
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare(
        "SELECT sc.comment, sc.created_at, u.name
         FROM short_comments sc
         JOIN users u ON u.id = sc.user_id
         WHERE sc.short_id = ?
         ORDER BY sc.created_at DESC LIMIT 50"
    );
    $stmt->bind_param('i', $shortId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $out  = array_map(fn($r) => [
        'name'    => sanitize($r['name']),
        'initial' => strtoupper($r['name'][0]),
        'text'    => sanitize($r['comment']),
    ], $rows);
    echo json_encode(['comments' => $out]);
    exit;
}

// ── POST: add comment
if (!isLoggedIn()) { echo json_encode(['error' => 'Not logged in']); exit; }
$comment = trim($_POST['comment'] ?? '');
$userId  = (int)$_SESSION['user_id'];
if (strlen($comment) < 1) { echo json_encode(['error' => 'Empty comment']); exit; }
$comment = substr($comment, 0, 500);

$stmt = $conn->prepare("INSERT INTO short_comments (short_id, user_id, comment) VALUES (?, ?, ?)");
$stmt->bind_param('iis', $shortId, $userId, $comment);
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
