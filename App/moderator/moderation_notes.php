<?php
// moderator/moderation_notes.php
require_once __DIR__ . '/_auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit;
}
if (!verify_mod_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(400); echo json_encode(['error'=>'Invalid CSRF']); exit;
}
$portfolio_id = (int)($_POST['portfolio_id'] ?? 0);
$note = trim((string)($_POST['note'] ?? ''));
$moderator_id = (int)$_SESSION['user_id'];

if ($portfolio_id <= 0 || $note === '') {
    http_response_code(400); echo json_encode(['error'=>'Missing data']); exit;
}

$mysqli = db_connect();
$stmt = $mysqli->prepare("INSERT INTO moderation_logs (portfolio_id, moderator_id, action, notes, created_at) VALUES (?, ?, 'note', ?, NOW())");
$stmt->bind_param('iis', $portfolio_id, $moderator_id, $note);
$ok = $stmt->execute();
$stmt->close();
$mysqli->query("INSERT INTO audit_logs (user_id, action, ip, created_at) VALUES ($moderator_id, 'moderator_note:$portfolio_id', '".$_SERVER['REMOTE_ADDR']."', NOW())");
$mysqli->close();

if ($ok) {
    echo json_encode(['ok'=>true]);
} else {
    http_response_code(500); echo json_encode(['error'=>'Failed to save note']);
}
