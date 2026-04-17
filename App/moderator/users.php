
<?php
// moderator/users.php
require_once __DIR__ . '/_auth.php';
session_start();

$mysqli = db_connect();

// Only allow moderator to manage students and recruiters
$allowed_roles = ['student','recruiter'];

/**
 * Flash message helper
 */
function flash($key) {
    if (!empty($_SESSION[$key])) {
        echo '<div class="alert '.$key.'">'.e($_SESSION[$key]).'</div>';
        unset($_SESSION[$key]);
    }
}

// Handle actions: suspend/reactivate/verify (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['user_id'], $_POST['csrf_token'])) {

    if (!verify_mod_csrf($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $uid = (int)$_POST['user_id'];
    $action = $_POST['action'];

    // Fetch target user's role
    $stmt = $mysqli->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->bind_result($target_role);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found || !in_array($target_role, $allowed_roles, true)) {
        $_SESSION['error'] = "Action not allowed.";
        header("Location: users.php");
        exit;
    }

    // Action mapping
    $actions_map = [
        'suspend' => [
            'query' => "UPDATE users SET status='suspended' WHERE id=?",
            'log'   => 'moderator_suspended',
            'msg'   => 'User suspended successfully.'
        ],
        'reactivate' => [
            'query' => "UPDATE users SET status='active' WHERE id=?",
            'log'   => 'moderator_reactivated',
            'msg'   => 'User reactivated successfully.'
        ],
        'verify' => [
            'query' => "UPDATE users SET is_verified=1 WHERE id=?",
            'log'   => 'moderator_verified',
            'msg'   => 'User verified successfully.'
        ]
    ];

    if (isset($actions_map[$action])) {

        // Update user
        $stmt = $mysqli->prepare($actions_map[$action]['query']);
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->close();

        // Insert audit log (SAFE)
        $stmt = $mysqli->prepare("INSERT INTO audit_logs (user_id, action, ip, created_at) VALUES (?, ?, ?, NOW())");
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param('iss', $uid, $actions_map[$action]['log'], $ip);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success'] = $actions_map[$action]['msg'];
    }

    header("Location: users.php");
    exit;
}

// Pagination & search
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 20;
$offset = ($page - 1) * $per;

$where = "WHERE role IN ('student','recruiter')";
$params = [];
$types = '';

if ($q !== '') {
    $where .= " AND (email LIKE ? OR name LIKE ? OR college LIKE ?)";
    $like = "%$q%";
    $params = [$like, $like, $like];
    $types = 'sss';
}

// Count
$countSql = "SELECT COUNT(*) as cnt FROM users $where";
$stmt = $mysqli->prepare($countSql);
if ($q !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Fetch users
$listSql = "SELECT id,name,email,role,college,is_verified,status,created_at 
            FROM users $where 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?";

if ($q !== '') {
    $stmt = $mysqli->prepare($listSql);
    $stmt->bind_param($types . 'ii', ...array_merge($params, [$per, $offset]));
} else {
    $stmt = $mysqli->prepare($listSql);
    $stmt->bind_param('ii', $per, $offset);
}

$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$mysqli->close();
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Users — Moderator</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="style.css">
</head>

<body>
<div class="container">

<header class="header">
    <div class="brand">
        <div class="logo">SP</div>
        <div>
            <strong>Users</strong>
            <div class="small">Students & Recruiters</div>
        </div>
    </div>
    <nav class="nav">
        <a class="btn" href="index.php">Home</a>
        <a class="btn" href="portfolios.php">Portfolios</a>
    </nav>
</header>

<section class="card">

<?php flash('success'); ?>
<?php flash('error'); ?>

<form method="get" class="form-inline" style="margin-bottom:12px">
    <input type="text" name="q" placeholder="Search email, name, college"
           value="<?= e($q) ?>">
    <button class="btn ghost">Search</button>
</form>

<table class="table">
<thead>
<tr>
<th>Name</th>
<th>Email</th>
<th>Role</th>
<th>College</th>
<th>Verified</th>
<th>Status</th>
<th>Joined</th>
<th>Actions</th>
</tr>
</thead>

<tbody>
<?php foreach ($users as $u): 
    $status = ($u['status'] === 'active') ? 'active' : 'suspended';
?>
<tr>
<td><?= e($u['name']) ?></td>
<td><?= e($u['email']) ?></td>
<td><?= e($u['role']) ?></td>
<td><?= e($u['college']) ?></td>

<td>
<span class="<?= $u['is_verified'] ? 'badge success' : 'badge warning' ?>">
<?= $u['is_verified'] ? 'Verified' : 'Pending' ?>
</span>
</td>

<td><?= e($status) ?></td>
<td><?= e($u['created_at']) ?></td>

<td>
<form method="post" style="display:inline">
<input type="hidden" name="csrf_token" value="<?= e(mod_csrf()) ?>">
<input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">

<?php if ($status === 'active'): ?>
<button class="btn ghost" name="action" value="suspend">Suspend</button>
<?php else: ?>
<button class="btn ghost" name="action" value="reactivate">Reactivate</button>
<?php endif; ?>

<?php if (!$u['is_verified']): ?>
<button class="btn" name="action" value="verify">Verify</button>
<?php endif; ?>

</form>
</td>

</tr>
<?php endforeach; ?>
</tbody>
</table>

<div class="small" style="margin-top:12px">
Showing <?= count($users) ?> of <?= (int)$total ?> users
</div>

</section>

<div class="footer">© StudentPort <?= date('Y') ?></div>

</div>
</body>
</html>
