<?php
// admin/index.php
require_once __DIR__ . '/_auth.php';
$mysqli = db_connect();

// KPIs
$kpis = [];
$q = $mysqli->query("SELECT 
  (SELECT COUNT(*) FROM users) AS total_users,
  (SELECT COUNT(*) FROM users WHERE role='student') AS students,
  (SELECT COUNT(*) FROM portfolios WHERE status='pending') AS pending_portfolios,
  (SELECT COUNT(*) FROM uploads WHERE scan_status='infected') AS infected_uploads,
  (SELECT SUM(size_bytes) FROM portfolios) AS total_storage
");
$kpis = $q->fetch_assoc();

// Recent activity (latest 12 audit logs)
$actStmt = $mysqli->prepare("SELECT a.created_at, a.action, u.email FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 12");
$actStmt->execute();
$actRes = $actStmt->get_result();
$activities = $actRes->fetch_all(MYSQLI_ASSOC);
$actStmt->close();
$mysqli->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><title>Admin Dashboard — StudentPort</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="brand"><div class="logo">SP</div><div><strong>StudentPort Admin</strong><div class="small">Control center</div></div></div>
      <nav class="nav">
        <a class="btn ghost" href="/index.php">View Site</a>
        <a class="btn" href="users.php">Users</a>
        <a class="btn" href="portfolios.php">Portfolios</a>
        <a class="btn" href="moderation.php">Moderation</a>
        <form method="post" action="../auth/logout.php" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf()) ?>">
          <button class="btn ghost" type="submit">Logout</button>
        </form>
      </nav>
    </header>

    <section class="card">
      <div class="kpis">
        <div class="kpi"><div class="small">Total users</div><div style="font-size:20px"><?= (int)$kpis['total_users'] ?></div></div>
        <div class="kpi"><div class="small">Students</div><div style="font-size:20px"><?= (int)$kpis['students'] ?></div></div>
        <div class="kpi"><div class="small">Pending portfolios</div><div style="font-size:20px"><?= (int)$kpis['pending_portfolios'] ?></div></div>
        <div class="kpi"><div class="small">Infected uploads</div><div style="font-size:20px"><?= (int)$kpis['infected_uploads'] ?></div></div>
        <div class="kpi"><div class="small">Total storage (bytes)</div><div style="font-size:16px"><?= (int)$kpis['total_storage'] ?></div></div>
      </div>

      <h3 style="margin-top:12px">Recent activity</h3>
      <div class="grid">
        <?php if (empty($activities)): ?>
          <div class="alert">No recent activity.</div>
        <?php else: ?>
          <table class="table">
            <thead><tr><th>When</th><th>User</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach ($activities as $a): ?>
                <tr>
                  <td><?= htmlspecialchars($a['created_at']) ?></td>
                  <td class="small"><?= htmlspecialchars($a['email'] ?? 'system') ?></td>
                  <td><?= htmlspecialchars($a['action']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </section>

    <div class="footer">© StudentPort <?= date('Y') ?> • Admin dashboard</div>
  </div>
</body>
</html>
