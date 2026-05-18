<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../DB/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!verify_recruiter_csrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$recruiterId = (int)$_SESSION['user_id'];
$portfolioId = (int)($_POST['portfolio_id'] ?? 0);
$action      = $_POST['action'] ?? '';

if ($portfolioId <= 0 || !in_array($action, ['add', 'remove'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

try {
    $mysqli = db_connect();

    // Verify portfolio is live and accessible
    $stmt = $mysqli->prepare("
        SELECT id FROM portfolios
        WHERE id = ?
          AND status = 'live'
          AND visibility IN ('public','recruiter_only')
    ");
    $stmt->bind_param('i', $portfolioId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$exists) {
        echo json_encode(['success' => false, 'error' => 'Portfolio not found']);
        $mysqli->close();
        exit;
    }

    if ($action === 'add') {
        // INSERT IGNORE to handle duplicate silently
        $stmt = $mysqli->prepare("
            INSERT IGNORE INTO shortlists (recruiter_id, portfolio_id, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->bind_param('ii', $recruiterId, $portfolioId);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true, 'action' => 'added']);

    } else {
        $stmt = $mysqli->prepare("
            DELETE FROM shortlists
            WHERE recruiter_id = ? AND portfolio_id = ?
        ");
        $stmt->bind_param('ii', $recruiterId, $portfolioId);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true, 'action' => 'removed']);
    }

    $mysqli->close();

} catch (Throwable $e) {
    error_log('toggle_shortlist error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
