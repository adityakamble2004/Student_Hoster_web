<?php
// admin/moderation.php
require_once __DIR__ . '/_auth.php';
$mysqli = db_connect();

// Fetch flagged portfolios (moderation_logs or uploads flagged)
// For MVP: show portfolios with status 'pending' or uploads with scan_status='infected'
$flagStmt = $mysqli->prepare("
  SELECT p.id,p.title,p.slug,p.status,p.published_at,u.name AS owner_name, up.scan_status, up.scan_report
  FROM portfolios p
  JOIN users u ON p.user_id = u.id
  LEFT JOIN uploads up ON up.portfolio_id = p.id
  WHERE p.status = 'pending' OR up.scan_status = 'infected'
  GROUP BY p.id
  ORDER BY p.created_at DESC
  LIMIT 50
");
$flagStmt->execute();
$flags = $flagStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$flagStmt->close();
$mysqli->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><title>Moderation — Admin</title><meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="brand"><div class="logo">SP</div><div><strong>Moderation</strong><div class="small">Review flagged items</div></div></div>
      <nav class="nav"><a class="btn" href="index.php">Dashboard</a><a class="btn" href="users.php">Users</a></nav>
    </header>

    <section class="card">
      <h3>Flagged items</h3>
      <?php if (empty($flags)): ?>
        <div class="alert">No flagged items at the moment.</div>
      <?php else: ?>
        <table class="table">
          <thead><tr><th>Portfolio</th><th>Owner</th><th>Status</th><th>Scan</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($flags as $f): ?>
              <tr>
                <td><?= htmlspecialchars($f['title'] ?: $f['slug']) ?></td>
                <td class="small"><?= htmlspecialchars($f['owner_name']) ?></td>
                <td><?= htmlspecialchars($f['status']) ?></td>
                <td class="small"><?= htmlspecialchars($f['scan_status'] ?? 'n/a') ?><div class="small"><?= htmlspecialchars(substr($f['scan_report'] ?? '',0,120)) ?></div></td>
                <td>
                  <a class="btn" href="portfolios.php?status=pending">Open</a>
                  <form method="post" action="portfolios.php" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf()) ?>">
                    <input type="hidden" name="portfolio_id" value="<?= (int)$f['id'] ?>">
                    <button class="btn ghost" name="action" value="rescan" type="submit">Re-scan</button>
                    <button class="btn ghost" name="action" value="remove" type="submit">Remove</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <div class="footer">© StudentPort <?= date('Y') ?></div>
  </div>
</body>
</html>
