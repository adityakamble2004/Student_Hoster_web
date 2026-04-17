<?php
// moderator/index.php
require_once __DIR__ . '/_auth.php';
$mysqli = db_connect();

// KPIs relevant to moderator
$kpiQ = $mysqli->query("
  SELECT
    (SELECT COUNT(*) FROM users WHERE role='student') AS students,
    (SELECT COUNT(*) FROM users WHERE role='recruiter') AS recruiters,
    (SELECT COUNT(*) FROM portfolios WHERE status='pending') AS pending_portfolios,
    (SELECT COUNT(*) FROM uploads WHERE scan_status='infected') AS infected_uploads
");
$kpis = $kpiQ->fetch_assoc();

// Moderation queue: pending portfolios (limit 20)
$queueStmt = $mysqli->prepare("
  SELECT p.id,p.title,p.slug,p.status,p.published_at,p.size_bytes,u.name AS owner_name,u.email AS owner_email
  FROM portfolios p
  JOIN users u ON p.user_id = u.id
  WHERE p.status = 'pending'
  ORDER BY p.created_at DESC
  LIMIT 20
");
$queueStmt->execute();
$queue = $queueStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$queueStmt->close();

// Recent moderation actions (audit_logs where action like 'moderator_%' or 'portfolio_%')
$actStmt = $mysqli->prepare("SELECT created_at, action FROM audit_logs WHERE action LIKE 'moderator_%' OR action LIKE 'portfolio_%' ORDER BY created_at DESC LIMIT 12");
$actStmt->execute();
$activities = $actStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$actStmt->close();
$mysqli->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><title>Moderator Dashboard — StudentPort</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="brand"><div class="logo">SP</div><div><strong>Moderator</strong><div class="small">Review & manage content</div></div></div>
      <nav class="nav">
        <a class="btn ghost" href="/index.php">View Site</a>
        <a class="btn" href="users.php">Users</a>
        <a class="btn" href="portfolios.php">Portfolios</a>
        <form method="post" action="/auth/logout.php" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= e(mod_csrf()) ?>">
          <button class="btn ghost" type="submit">Logout</button>
        </form>
      </nav>
    </header>

    <section class="card">
      <div class="kpis">
        <div class="kpi"><div class="small">Students</div><div style="font-size:20px"><?= (int)$kpis['students'] ?></div></div>
        <div class="kpi"><div class="small">Recruiters</div><div style="font-size:20px"><?= (int)$kpis['recruiters'] ?></div></div>
        <div class="kpi"><div class="small">Pending portfolios</div><div style="font-size:20px"><?= (int)$kpis['pending_portfolios'] ?></div></div>
        <div class="kpi"><div class="small">Infected uploads</div><div style="font-size:20px"><?= (int)$kpis['infected_uploads'] ?></div></div>
      </div>

      <h3>Moderation queue (pending portfolios)</h3>
      <?php if (empty($queue)): ?>
        <div class="alert">No pending portfolios to review.</div>
      <?php else: ?>
        <table class="table">
          <thead><tr><th>Title</th><th>Owner</th><th>Size</th><th>Published</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($queue as $p): ?>
              <tr>
                <td><?= e($p['title'] ?: $p['slug']) ?></td>
                <td class="small"><?= e($p['owner_name']) ?><div class="small"><?= e($p['owner_email']) ?></div></td>
                <td class="small"><?= (int)$p['size_bytes'] ?> bytes</td>
                <td class="small"><?= e($p['published_at']) ?></td>
                <td>
                  <form method="post" action="portfolios.php" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= e(mod_csrf()) ?>">
                    <input type="hidden" name="portfolio_id" value="<?= (int)$p['id'] ?>">
                    <button class="btn" name="action" value="approve" type="submit">Approve</button>
                    <button class="btn ghost" name="action" value="request_edit" type="submit">Request Edit</button>
                    <button class="btn ghost" name="action" value="remove" type="submit">Remove</button>
                    <button class="btn ghost" name="action" value="quarantine" type="submit">Quarantine</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <h3 style="margin-top:16px">Recent moderation activity</h3>
      <?php if (empty($activities)): ?>
        <div class="small">No recent actions.</div>
      <?php else: ?>
        <table class="table">
          <thead><tr><th>When</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($activities as $a): ?>
              <tr><td><?= e($a['created_at']) ?></td><td><?= e($a['action']) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <div class="footer">© StudentPort <?= date('Y') ?> • Moderator console</div>
  </div>
</body>
</html>
