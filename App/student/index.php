<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../DB/db_connect.php';

/* ================= CONFIG ================= */
define('BASE_URL', '/App'); // IMPORTANT: project base path
define('SECRET_KEY', 'change_this_to_random_secure_key_123456');
define('PRIVATE_LINK_TTL', 86400);

/* ================= INIT ================= */
$mysqli = db_connect();
$userId = (int)$_SESSION['user_id'];

/* ================= HELPERS ================= */
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(string $path = ''): string {
    return BASE_URL . $path;
}

function make_signed_preview_url(string $slug, int $ttl = PRIVATE_LINK_TTL): string {
    $exp = time() + $ttl;
    $data = $slug . '|' . $exp;
    $sig = hash_hmac('sha256', $data, SECRET_KEY);
    return base_url('/student/preview.php?' . http_build_query([
        'slug' => $slug,
        'exp'  => $exp,
        'sig'  => $sig
    ]));
}

function log_action(mysqli $db, int $userId, string $action): void {
    $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, ip, created_at) VALUES (?, ?, ?, NOW())");
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt->bind_param("iss", $userId, $action, $ip);
    $stmt->execute();
    $stmt->close();
}

/* ================= HANDLE ACTIONS ================= */
$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_student_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {

        $action = $_POST['action'] ?? '';
        $portfolio_id = (int)($_POST['portfolio_id'] ?? 0);

        if ($portfolio_id <= 0) {
            $errors[] = 'Invalid portfolio.';
        } else {
            try {

                if ($action === 'set_visibility') {
                    $visibility = $_POST['visibility'] ?? 'public';

                    $stmt = $mysqli->prepare("UPDATE portfolios SET visibility=?, updated_at=NOW() WHERE id=? AND user_id=?");
                    $stmt->bind_param("sii", $visibility, $portfolio_id, $userId);
                    $stmt->execute();
                    $stmt->close();

                    log_action($mysqli, $userId, "set_visibility:$portfolio_id:$visibility");
                    $messages[] = 'Visibility updated.';
                }

                elseif ($action === 'unpublish') {
                    $stmt = $mysqli->prepare("UPDATE portfolios SET status='removed', updated_at=NOW() WHERE id=? AND user_id=?");
                    $stmt->bind_param("ii", $portfolio_id, $userId);
                    $stmt->execute();
                    $stmt->close();

                    log_action($mysqli, $userId, "unpublish:$portfolio_id");
                    $messages[] = 'Portfolio unpublished.';
                }

                elseif ($action === 'republish') {
                    $stmt = $mysqli->prepare("UPDATE portfolios SET status='live', published_at=NOW(), updated_at=NOW() WHERE id=? AND user_id=?");
                    $stmt->bind_param("ii", $portfolio_id, $userId);
                    $stmt->execute();
                    $stmt->close();

                    log_action($mysqli, $userId, "republish:$portfolio_id");
                    $messages[] = 'Portfolio republished.';
                }

                elseif ($action === 'delete') {
                    $stmt = $mysqli->prepare("UPDATE portfolios SET status='removed', visibility='private_link' WHERE id=? AND user_id=?");
                    $stmt->bind_param("ii", $portfolio_id, $userId);
                    $stmt->execute();
                    $stmt->close();

                    log_action($mysqli, $userId, "delete:$portfolio_id");
                    $messages[] = 'Portfolio removed.';
                }

                else {
                    $errors[] = 'Unknown action.';
                }

            } catch (Throwable $ex) {
                error_log($ex->getMessage());
                $errors[] = 'Something went wrong.';
            }
        }
    }
}

/* ================= FETCH DATA ================= */
$stmt = $mysqli->prepare("SELECT * FROM portfolios WHERE user_id=? ORDER BY created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$portfolios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$mysqli->close();
?>

<!DOCTYPE html>
<html>
<head>
<title>Student Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
body{font-family:Arial;background:#f4f6f9;margin:0;padding:20px}
.card{background:#fff;padding:15px;border-radius:8px;margin-bottom:15px}
.btn{padding:6px 10px;background:#2563eb;color:#fff;border:none;border-radius:5px;cursor:pointer}
.btn.ghost{background:#e5e7eb;color:#000}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px;border-bottom:1px solid #ddd}
</style>
</head>

<body>

<h2>Your Portfolios</h2>

<?php foreach ($errors as $e): ?>
<div class="card" style="background:#fee2e2"><?= e($e) ?></div>
<?php endforeach; ?>

<?php foreach ($messages as $m): ?>
<div class="card" style="background:#dcfce7"><?= e($m) ?></div>
<?php endforeach; ?>

<?php if (empty($portfolios)): ?>
<div class="card">No portfolios yet</div>
<?php else: ?>

<table class="table">
<tr>
<th>Title</th>
<th>Status</th>
<th>Visibility</th>
<th>Actions</th>
</tr>

<?php foreach ($portfolios as $p): ?>
<tr>

<td><?= e($p['title'] ?: $p['slug']) ?></td>
<td><?= e($p['status']) ?></td>
<td><?= e($p['visibility']) ?></td>

<td>

<a class="btn ghost" href="<?= base_url('/student/preview.php?slug=' . urlencode($p['slug'])) ?>" target="_blank">Preview</a>

<?php $signed = make_signed_preview_url($p['slug']); ?>
<button class="btn" onclick="navigator.clipboard.writeText('<?= e($signed) ?>')">Copy Link</button>

<form method="post" style="display:inline">
<input type="hidden" name="csrf_token" value="<?= e(student_csrf()) ?>">
<input type="hidden" name="portfolio_id" value="<?= $p['id'] ?>">

<button class="btn ghost" name="action" value="unpublish">Unpublish</button>
<button class="btn ghost" name="action" value="delete">Delete</button>
</form>

</td>

</tr>
<?php endforeach; ?>

</table>

<?php endif; ?>

</body>
</html>