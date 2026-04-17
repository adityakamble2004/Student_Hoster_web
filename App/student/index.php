<?php
// student/index.php
// Student dashboard acting as controller: shows portfolios/uploads, profile button,
// upload form (stores ZIP in uploads/), enqueues job (Redis or MySQL fallback).
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';            // student guard, student_csrf(), e()
require_once __DIR__ . '/../DB/db_connect.php';    // db_connect()

$mysqli = db_connect();
$userId = (int)$_SESSION['user_id'];

$errors = [];
$messages = [];

/* ---------- Handle POST: file upload ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    if (!verify_student_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } elseif (!isset($_FILES['zip']) || $_FILES['zip']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please choose a ZIP file to upload.';
    } else {
        $file = $_FILES['zip'];
        $maxSize = 50 * 1024 * 1024; // 50MB
        if ($file['size'] > $maxSize) {
            $errors[] = 'File exceeds 50MB limit.';
        } else {
            // Basic MIME check
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $allowedMimes = ['application/zip','application/x-zip-compressed','application/octet-stream'];
            if (!in_array($mime, $allowedMimes, true)) {
                $errors[] = 'Only ZIP files are allowed.';
            } else {
                // Prepare storage
                $uploadDir = __DIR__ . '/../uploads';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    $errors[] = 'Unable to create uploads directory.';
                } else {
                    $origName = basename($file['name']);
                    $storedName = sprintf('%d_%s.zip', $userId, bin2hex(random_bytes(8)));
                    $dest = $uploadDir . '/' . $storedName;

                    if (!move_uploaded_file($file['tmp_name'], $dest)) {
                        $errors[] = 'Failed to save uploaded file.';
                    } else {
                        // Insert uploads record
                        $stmt = $mysqli->prepare(
                            'INSERT INTO uploads (portfolio_id, user_id, original_filename, stored_filename, size_bytes, uploaded_at, scan_status)
                             VALUES (NULL, ?, ?, ?, ?, NOW(), "pending")'
                        );
                        $stmt->bind_param('isss', $userId, $origName, $storedName, $file['size']);
                        $ok = $stmt->execute();
                        $uploadId = $stmt->insert_id;
                        $stmt->close();

                        if (!$ok) {
                            $errors[] = 'Database error while recording upload.';
                            // cleanup file
                            @unlink($dest);
                        } else {
                            // Enqueue job: try Redis, fallback to job_queue table
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
                                // ensure fallback table exists
                                $mysqli->query(
                                    "CREATE TABLE IF NOT EXISTS job_queue (
                                      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                                      payload JSON NOT NULL,
                                      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                      processed TINYINT(1) NOT NULL DEFAULT 0
                                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
                                );
                                $pstmt = $mysqli->prepare('INSERT INTO job_queue (payload) VALUES (?)');
                                $payload = json_encode(['upload_id' => $uploadId]);
                                $pstmt->bind_param('s', $payload);
                                $pstmt->execute();
                                $pstmt->close();
                            }

                            // Audit log
                            $mysqli->query("INSERT INTO audit_logs (user_id, action, ip, created_at) VALUES ($userId, 'upload_created:$uploadId', '".$_SERVER['REMOTE_ADDR']."', NOW())");

                            $messages[] = 'Upload received and queued for processing.';
                        }
                    }
                }
            }
        }
    }
}

/* ---------- Fetch student's portfolios and uploads ---------- */
// portfolios
$portfolios = [];
$pstmt = $mysqli->prepare('SELECT id, title, slug, visibility, status, size_bytes, published_at, created_at FROM portfolios WHERE user_id = ? ORDER BY created_at DESC');
$pstmt->bind_param('i', $userId);
$pstmt->execute();
$portfolios = $pstmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pstmt->close();

// uploads
$uploads = [];
$ustmt = $mysqli->prepare('SELECT id, original_filename, stored_filename, size_bytes, uploaded_at, scan_status, scan_report, portfolio_id FROM uploads WHERE user_id = ? ORDER BY uploaded_at DESC');
$ustmt->bind_param('i', $userId);
$ustmt->execute();
$uploads = $ustmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ustmt->close();

$mysqli->close();

/* ---------- Helpers ---------- */
function make_signed_preview_url(string $slug, int $ttl = 86400): string {
    // keep secret key in env in production
    $secret = getenv('STUDENTPORT_PREVIEW_KEY') ?: 'change_this_secret';
    $exp = time() + $ttl;
    $data = $slug . '|' . $exp;
    $sig = hash_hmac('sha256', $data, $secret);
    return '/student/preview.php?' . http_build_query(['slug' => $slug, 'exp' => $exp, 'sig' => $sig]);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Student Dashboard — StudentPort</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
  <style>
    .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
    .grid{display:grid;grid-template-columns:1fr 420px;gap:16px}
    .card{padding:14px;border-radius:10px;background:#fff}
    .small{font-size:0.9rem;color:#6b7280}
    .btn{padding:8px 12px;border-radius:8px;background:#34d399;color:#04263b;border:none;font-weight:700;cursor:pointer}
    .btn.ghost{background:transparent;border:1px solid #e6eef6}
    .table{width:100%;border-collapse:collapse}
    .table th,.table td{padding:8px;border-bottom:1px solid #eef2f7;text-align:left}
    .alert{background:#fff3cd;padding:10px;border-radius:8px;color:#664d03}
  </style>
</head>
<body>
  <div class="container">
    <div class="topbar">
      <div>
        <h2>Your Dashboard</h2>
        <div class="small">Manage uploads and portfolios</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <a class="btn ghost" href="/index.php">Home</a>
        <a class="btn" href="/student/profile.php">Profile</a>
      </div>
    </div>

    <?php if ($errors): ?>
      <div class="card" style="background:#fff5f5;color:#842029;margin-bottom:12px">
        <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($messages): ?>
      <div class="card" style="background:#ecfdf5;color:#064e3b;margin-bottom:12px">
        <?php foreach ($messages as $m): ?><div><?= e($m) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="grid">
      <!-- Left: uploads & portfolios -->
      <div>
        <div class="card" style="margin-bottom:12px">
          <h3>Uploads</h3>
          <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center">
            <input type="hidden" name="csrf_token" value="<?= e(student_csrf()) ?>">
            <input type="hidden" name="action" value="upload">
            <input type="file" name="zip" accept=".zip" required>
            <button class="btn" type="submit">Upload ZIP</button>
          </form>
          <div class="small" style="margin-top:8px">Accepted: .zip • Max 50MB</div>

          <?php if (empty($uploads)): ?>
            <div class="alert" style="margin-top:12px">No uploads yet.</div>
          <?php else: ?>
            <table class="table" style="margin-top:12px">
              <thead><tr><th>File</th><th>Size</th><th>Uploaded</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($uploads as $u): ?>
                  <tr>
                    <td><?= e($u['original_filename']) ?><?php if ($u['portfolio_id']): ?> <div class="small">Published as #<?= (int)$u['portfolio_id'] ?></div><?php endif; ?></td>
                    <td class="small"><?= (int)$u['size_bytes'] ?> bytes</td>
                    <td class="small"><?= e($u['uploaded_at']) ?></td>
                    <td class="small"><?= e($u['scan_status']) ?></td>
                    <td>
                      <a class="btn ghost" href="upload_detail.php?id=<?= (int)$u['id'] ?>">View</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <div class="card">
          <h3>Portfolios</h3>
          <?php if (empty($portfolios)): ?>
            <div class="alert">No portfolios published yet.</div>
          <?php else: ?>
            <table class="table">
              <thead><tr><th>Title</th><th>Status</th><th>Visibility</th><th>Published</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($portfolios as $p): ?>
                  <tr>
                    <td><strong><?= e($p['title'] ?: $p['slug']) ?></strong><div class="small"><?= e($p['slug']) ?></div></td>
                    <td class="small"><?= e($p['status']) ?></td>
                    <td class="small"><?= e($p['visibility']) ?></td>
                    <td class="small"><?= e($p['published_at'] ?? $p['created_at']) ?></td>
                    <td>
                      <?php if ($p['status'] === 'live' && $p['visibility'] === 'public'): ?>
                        <a class="btn ghost" href="/public/portfolios/<?= rawurlencode($p['slug']) ?>/index.html" target="_blank" rel="noopener">Open</a>
                      <?php else: ?>
                        <a class="btn ghost" href="<?= e(make_signed_preview_url($p['slug'])) ?>" target="_blank" rel="noopener">Preview</a>
                      <?php endif; ?>
                      <a class="btn" href="upload_detail.php?portfolio_id=<?= (int)$p['id'] ?>">Manage</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right: quick profile & help -->
      <aside>
        <div class="card" style="margin-bottom:12px">
          <h4>Profile</h4>
          <div class="small">Update your name, email, and college.</div>
          <div style="margin-top:8px">
            <a class="btn" href="/student/profile.php">Edit Profile</a>
          </div>
        </div>

        <div class="card">
          <h4>Help & status</h4>
          <div class="small">Uploads are scanned and validated. Processing may take a few minutes.</div>
          <div style="margin-top:8px">
            <a class="btn ghost" href="/student/faq.php">FAQ</a>
          </div>
        </div>
      </aside>
    </div>
  </div>
</body>
</html>
