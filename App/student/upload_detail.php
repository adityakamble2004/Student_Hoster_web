<?php
// student/upload_detail.php
require_once __DIR__ . '/_auth.php';
$mysqli = db_connect();
$userId = (int)$_SESSION['user_id'];
$id = max(0, (int)($_GET['id'] ?? 0));

$stmt = $mysqli->prepare("SELECT id, portfolio_id, original_filename, stored_filename, size_bytes, uploaded_at, scan_status, scan_report, processed_at FROM uploads WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $id, $userId);
$stmt->execute();
$upload = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$upload) {
    http_response_code(404);
    echo "Upload not found.";
    exit;
}

// Download original ZIP
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $file = __DIR__ . '/../uploads/' . $upload['stored_filename'];
    if (is_file($file)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.basename($upload['original_filename']).'"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    } else {
        echo "File not available.";
        exit;
    }
}

$mysqli->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><title>Upload Detail — StudentPort</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <header class="header">
      <div><strong>Upload details</strong><div class="small"><?= e($upload['original_filename']) ?></div></div>
      <nav><a class="btn ghost" href="dashboard.php">Back</a></nav>
    </header>

    <section class="card">
      <div><strong>Uploaded:</strong> <span class="small"><?= e($upload['uploaded_at']) ?></span></div>
      <div><strong>Size:</strong> <span class="small"><?= (int)$upload['size_bytes'] ?> bytes</span></div>
      <div><strong>Status:</strong> <span class="small"><?= e($upload['scan_status']) ?></span></div>
      <?php if (!empty($upload['scan_report'])): ?>
        <div style="margin-top:12px"><strong>Scan report</strong>
          <pre style="background:#f3f6f9;padding:10px;border-radius:8px;color:#0b1220;white-space:pre-wrap"><?= e($upload['scan_report']) ?></pre>
        </div>
      <?php endif; ?>

      <div style="margin-top:12px;display:flex;gap:8px">
        <a class="btn" href="?id=<?= (int)$upload['id'] ?>&action=download">Download ZIP</a>
        <form method="post" action="dashboard.php" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= e(student_csrf()) ?>">
          <input type="hidden" name="upload_id" value="<?= (int)$upload['id'] ?>">
          <button class="btn ghost" name="action" value="rescan" type="submit">Request Re-scan</button>
          <button class="btn ghost" name="action" value="delete" type="submit">Delete</button>
        </form>
      </div>

      <?php if ($upload['portfolio_id']): ?>
        <div style="margin-top:12px"><strong>Published portfolio ID:</strong> <span class="small"><?= (int)$upload['portfolio_id'] ?></span></div>
      <?php endif; ?>
    </section>
  </div>
</body>
</html>
