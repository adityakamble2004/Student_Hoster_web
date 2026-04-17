<?php
// admin/users.php
require_once __DIR__ . '/_auth.php';
$mysqli = db_connect();

// Handle actions: suspend/reactivate/verify (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['user_id'], $_POST['csrf_token'])) {
    if (!verify_admin_csrf($_POST['csrf_token'])) { die('Invalid CSRF'); }
    $uid = (int)$_POST['user_id'];
    $action = $_POST['action'];
    if ($action === 'suspend') {
        $stmt = $mysqli->prepare("UPDATE users SET status='suspended' WHERE id=?");
        $stmt->bind_param('i',$uid); $stmt->execute(); $stmt->close();
        $mysqli->query("INSERT INTO audit_logs (user_id, action, ip, created_at) VALUES ($uid, 'suspended_by_admin', '".$_SERVER['REMOTE_ADDR']."', NOW())");
    } elseif ($action === 'reactivate') {
        $stmt = $mysqli->prepare("UPDATE users SET status='active' WHERE id=?");
        $stmt->bind_param('i',$uid); $stmt->execute(); $stmt->close();
        $mysqli->query("INSERT INTO audit_logs (user_id, action, ip, created_at) VALUES ($uid, 'reactivated_by_admin', '".$_SERVER['REMOTE_ADDR']."', NOW())");
    } elseif ($action === 'verify') {
        $stmt = $mysqli->prepare("UPDATE users SET is_verified=1 WHERE id=?");
        $stmt->bind_param('i',$uid); $stmt->execute(); $stmt->close();
        $mysqli->query("INSERT INTO audit_logs (user_id, action, ip, created_at) VALUES ($uid, 'verified_by_admin', '".$_SERVER['REMOTE_ADDR']."', NOW())");
    }
}

// Pagination & search
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 20;
$offset = ($page-1)*$per;

$params = [];
$sqlWhere = '';
if ($q !== '') {
    $sqlWhere = "WHERE (email LIKE ? OR name LIKE ? OR college LIKE ?)";
    $like = "%$q%";
    $params = [$like,$like,$like];
}

$countSql = "SELECT COUNT(*) as cnt FROM users $sqlWhere";
$stmt = $mysqli->prepare($countSql);
if ($q !== '') $stmt->bind_param('sss', ...$params);
$stmt->execute(); $res = $stmt->get_result(); $total = $res->fetch_assoc()['cnt']; $stmt->close();

$listSql = "SELECT id,name,email,role,college,is_verified,status,created_at FROM users $sqlWhere ORDER BY created_at DESC LIMIT ? OFFSET ?";
if ($q !== '') {
    $stmt = $mysqli->prepare($listSql);
    $stmt->bind_param('sssii', $params[0], $params[1], $params[2], $per, $offset);
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
  <meta charset="utf-8"><title>Users — Admin</title><meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="brand"><div class="logo">SP</div><div><strong>Users</strong><div class="small">Manage accounts</div></div></div>
      <nav class="nav"><a class="btn" href="index.php">Dashboard</a><a class="btn" href="portfolios.php">Portfolios</a></nav>
    </header>

    <section class="card">
      <form method="get" class="form-inline" style="margin-bottom:12px">
        <input type="text" name="q" placeholder="Search email, name, college" value="<?= htmlspecialchars($q) ?>" style="padding:8px;border-radius:8px;border:1px solid rgba(255,255,255,0.04);background:transparent;color:var(--text)">
        <button class="btn ghost" type="submit">Search</button>
      </form>

      <table class="table" role="table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>College</th><th>Verified</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?= htmlspecialchars($u['name']) ?></td>
              <td class="small"><?= htmlspecialchars($u['email']) ?></td>
              <td><?= htmlspecialchars($u['role']) ?></td>
              <td class="small"><?= htmlspecialchars($u['college']) ?></td>
              <td><?= $u['is_verified'] ? 'Yes' : 'No' ?></td>
              <td><?= htmlspecialchars($u['status']) ?></td>
              <td class="small"><?= htmlspecialchars($u['created_at']) ?></td>
              <td>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf()) ?>">
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <?php if ($u['status'] === 'active'): ?>
                    <button class="btn ghost" name="action" value="suspend" type="submit">Suspend</button>
                  <?php else: ?>
                    <button class="btn ghost" name="action" value="reactivate" type="submit">Reactivate</button>
                  <?php endif; ?>
                  <?php if (!$u['is_verified']): ?>
                    <button class="btn" name="action" value="verify" type="submit">Verify</button>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="small" style="margin-top:12px">Showing <?= count($users) ?> of <?= (int)$total ?> users</div>
    </section>

    <div class="footer">© StudentPort <?= date('Y') ?></div>
  </div>
</body>
</html>
