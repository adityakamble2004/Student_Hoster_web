<?php
// admin/portfolios.php
require_once __DIR__ . '/_auth.php';
$mysqli = db_connect();

// Handle actions: approve/remove/re-scan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['portfolio_id'], $_POST['csrf_token'])) {
    if (!verify_admin_csrf($_POST['csrf_token'])) { die('Invalid CSRF'); }
    $pid = (int)$_POST['portfolio_id'];
    $action = $_POST['action'];
    if ($action === 'approve') {
        $stmt = $mysqli->prepare("UPDATE portfolios SET status='approved', published_at=NOW() WHERE id=?");
        $stmt->bind_param('i',$pid); $stmt->execute(); $stmt->close();
        $mysqli->query("INSERT INTO audit_logs (user_id, action, ip, created_at) VALUES (NULL, 'portfolio_approved:$pid', '".$_SERVER['REMOTE_ADDR']."', NOW())");
    } elseif ($action === 'remove') {
        $stmt = $mysqli->prepare("UPDATE portfolios SET status='removed' WHERE id=?");
        $stmt->bind_param('i',$pid); $stmt->execute(); $stmt->close();
        $mysqli->query("INSERT INTO audit_logs (user_id, action, ip, created_at) VALUES (NULL, 'portfolio_removed:$pid', '".$_SERVER['REMOTE_ADDR']."', NOW())");
    } elseif ($action === 'rescan') {
        $stmt = $mysqli->prepare("UPDATE uploads SET scan_status='pending' WHERE portfolio_id=?");
        $stmt->bind_param('i',$pid); $stmt->execute(); $stmt->close();
        $mysqli->query("INSERT INTO audit_logs (user_id, action, ip, created_at) VALUES (NULL, 'rescan_requested:$pid', '".$_SERVER['REMOTE_ADDR']."', NOW())");
    }
}

// Filters & pagination
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 20; $offset = ($page-1)*$per;

$where = '';
$params = [];
if ($status !== '') { $where = "WHERE p.status = ?"; $params[] = $status; }

$countSql = "SELECT COUNT(*) as cnt FROM portfolios p $where";
$stmt = $mysqli->prepare($countSql);
if ($status !== '') { $stmt->bind_param('s', $params[0]); }
$stmt->execute(); $total = $stmt->get_result()->fetch_assoc()['cnt']; $stmt->close();

$listSql = "SELECT p.id,p.title,p.slug,p.visibility,p.status,p.published_at,p.size_bytes,u.name AS owner_name,u.email AS owner_email
            FROM portfolios p JOIN users u ON p.user_id = u.id $where ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
if ($status !== '') {
    $stmt = $mysqli->prepare($listSql);
    $stmt->bind_param('sii', $params[0], $per, $offset);
} else {
    $stmt = $mysqli->prepare($listSql);
    $stmt->bind_param('ii', $per, $offset);
}
$stmt->execute();
$portfolios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$mysqli->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><title>Portfolios — Admin</title><meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="brand"><div class="logo">SP</div><div><strong>Portfolios</strong><div class="small">Manage uploads</div></div></div>
      <nav class="nav"><a class="btn" href="index.php">Dashboard</a><a class="btn" href="users.php">Users</a></nav>
    </header>

    <section class="card">
      <div style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
        <form method="get" class="form-inline">
          <select name="status" style="padding:8px;border-radius:8px;background:transparent;border:1px solid rgba(255,255,255,0.04);color:var(--text)">
            <option value="">All statuses</option>
            <option value="pending" <?= ($status==='pending')?'selected':'' ?>>Pending</option>
            <option value="approved" <?= ($status==='approved')?'selected':'' ?>>Approved</option>
            <option value="live" <?= ($status==='live')?'selected':'' ?>>Live</option>
            <option value="removed" <?= ($status==='removed')?'selected':'' ?>>Removed</option>
          </select>
          <button class="btn ghost" type="submit">Filter</button>
        </form>
      </div>

      <table class="table">
        <thead><tr><th>Title</th><th>Owner</th><th>Visibility</th><th>Status</th><th>Size</th><th>Published</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($portfolios as $p): ?>
            <tr>
              <td><?= htmlspecialchars($p['title'] ?: $p['slug']) ?></td>
              <td class="small"><?= htmlspecialchars($p['owner_name']) ?> <div class="small"><?= htmlspecialchars($p['owner_email']) ?></div></td>
              <td><?= htmlspecialchars($p['visibility']) ?></td>
              <td><?= htmlspecialchars($p['status']) ?></td>
              <td class="small"><?= (int)$p['size_bytes'] ?> bytes</td>
              <td class="small"><?= htmlspecialchars($p['published_at']) ?></td>
              <td>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf()) ?>">
                  <input type="hidden" name="portfolio_id" value="<?= (int)$p['id'] ?>">
                  <?php if ($p['status'] !== 'approved' && $p['status'] !== 'live'): ?>
                    <button class="btn" name="action" value="approve" type="submit">Approve</button>
                  <?php endif; ?>
                  <button class="btn ghost" name="action" value="rescan" type="submit">Re-scan</button>
                  <button class="btn ghost" name="action" value="remove" type="submit">Remove</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="small" style="margin-top:12px">Showing <?= count($portfolios) ?> of <?= (int)$total ?> portfolios</div>
    </section>

    <div class="footer">© StudentPort <?= date('Y') ?></div>
  </div>
</body>
</html>
