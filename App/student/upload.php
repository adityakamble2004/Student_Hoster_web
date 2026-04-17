<?php
// student/upload.php
require_once __DIR__ . '/_auth.php';
$mysqli = db_connect();

$errors = [];
$success = null;
$maxSize = 50 * 1024 * 1024; // 50MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_student_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request (CSRF).';
    }

    if (!isset($_FILES['zip']) || $_FILES['zip']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please select a ZIP file to upload.';
    } else {
        $file = $_FILES['zip'];
        if ($file['size'] > $maxSize) $errors[] = 'File exceeds maximum allowed size of 50MB.';
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if ($mime !== 'application/zip' && $mime !== 'application/x-zip-compressed' && $mime !== 'application/octet-stream') {
            $errors[] = 'Only ZIP files are allowed.';
        }
    }

    if (empty($errors)) {
        $userId = (int)$_SESSION['user_id'];
        $origName = basename($file['name']);
        $storedName = sprintf('%s_%s.zip', $userId, bin2hex(random_bytes(6)));
        $uploadDir = __DIR__ . '/../uploads';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $dest = $uploadDir . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $errors[] = 'Failed to save uploaded file.';
        } else {
            // Insert uploads record
            $stmt = $mysqli->prepare("INSERT INTO uploads (portfolio_id, user_id, original_filename, stored_filename, size_bytes, uploaded_at, scan_status) VALUES (NULL, ?, ?, ?, ?, NOW(), 'pending')");
            $stmt->bind_param('isss', $userId, $origName, $storedName, $file['size']);
            $stmt->execute();
            $uploadId = $stmt->insert_id;
            $stmt->close();

            // Enqueue job: try Redis, fallback to MySQL job table
            $enqueued = false;
            if (extension_loaded('redis')) {
                try {
                    $r = new Redis();
                    $r->connect('127.0.0.1', 6379);
                    $payload = json_encode(['upload_id' => $uploadId]);
                    $r->lPush('upload_jobs', $payload);
                    $enqueued = true;
                } catch (Throwable $e) {
                    error_log('Redis enqueue failed: ' . $e->getMessage());
                }
            }
            if (!$enqueued) {
                // Fallback: insert into job_queue table (create table if not exists)
                $mysqli->query("CREATE TABLE IF NOT EXISTS job_queue (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, payload JSON NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, processed TINYINT(1) NOT NULL DEFAULT 0)");
                $pstmt = $mysqli->prepare("INSERT INTO job_queue (payload) VALUES (?)");
                $payload = json_encode(['upload_id' => $uploadId]);
                $pstmt->bind_param('s', $payload);
                $pstmt->execute();
                $pstmt->close();
            }

            // Audit log
            $mysqli->query("INSERT INTO audit_logs (user_id, action, ip, created_at) VALUES ($userId, 'upload_created:$uploadId', '".$_SERVER['REMOTE_ADDR']."', NOW())");

            $success = 'Upload received. Processing will start shortly.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><title>Upload — StudentPort</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <header class="header">
      <div><strong>Upload Portfolio</strong><div class="small">Upload a ZIP (max 50MB)</div></div>
      <nav><a class="btn ghost" href="dashboard.php">Back</a></nav>
    </header>

    <section class="card">
      <?php if ($errors): ?>
        <div class="alert"><?= e(implode(' ', $errors)) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="card" style="background:#ecfdf5;color:#064e3b"><?= e($success) ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(student_csrf()) ?>">
        <label class="small">Select ZIP file</label>
        <input type="file" name="zip" accept=".zip" required>
        <div style="margin-top:12px">
          <button class="btn" type="submit">Upload</button>
        </div>
      </form>

      <div class="small" style="margin-top:12px">Accepted: .zip • Max 50MB • We will scan and validate your files before publishing.</div>
    </section>
  </div>
</body>
</html>
