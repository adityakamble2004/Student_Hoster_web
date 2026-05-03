<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../DB/db_connect.php';

$mysqli = db_connect();
$userId = (int)$_SESSION['user_id'];

$errors = [];
$messages = [];

/* ---------- BASE URL ---------- */
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$projectPath = "/Student%20portfolio%20hoster/App/student/public/portfolios/";
$baseUrl = $protocol . $host . $projectPath;

/* ---------- HANDLE ACTIONS ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!verify_student_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {

        $action = $_POST['action'] ?? '';

        /* ---------- UPLOAD ---------- */
        if ($action === 'upload') {

            if (!isset($_FILES['zip']) || $_FILES['zip']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Select a ZIP file.';
            } else {

                $file = $_FILES['zip'];
                $storedName = $userId . '_' . bin2hex(random_bytes(8)) . '.zip';
                $dest = __DIR__ . '/../uploads/' . $storedName;

                move_uploaded_file($file['tmp_name'], $dest);

                // DB insert
                $stmt = $mysqli->prepare("
                    INSERT INTO uploads (user_id, original_filename, stored_filename, size_bytes)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("issi", $userId, $file['name'], $storedName, $file['size']);
                $stmt->execute();
                $uploadId = $stmt->insert_id;
                $stmt->close();

                // queue
                $payload = json_encode(['upload_id' => $uploadId]);
                $stmt = $mysqli->prepare("INSERT INTO job_queue (payload) VALUES (?)");
                $stmt->bind_param("s", $payload);
                $stmt->execute();
                $stmt->close();

                $messages[] = "Uploaded & queued 🚀";
            }
        }

        /* ---------- DELETE ---------- */
        if ($action === 'delete') {

            $portfolioId = (int)$_POST['portfolio_id'];

            $stmt = $mysqli->prepare("SELECT slug FROM portfolios WHERE id=? AND user_id=?");
            $stmt->bind_param("ii", $portfolioId, $userId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($res) {
                $path = __DIR__ . "/public/portfolios/" . $res['slug'];

                deleteFolder($path);

                $mysqli->query("UPDATE portfolios SET status='removed' WHERE id=$portfolioId");

                $messages[] = "Portfolio deleted 🗑️";
            }
        }

        /* ---------- REUPLOAD ---------- */
        if ($action === 'reupload') {

            $portfolioId = (int)$_POST['portfolio_id'];

            if (!isset($_FILES['zip']) || $_FILES['zip']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Select a ZIP file.';
            } else {

                $stmt = $mysqli->prepare("SELECT slug FROM portfolios WHERE id=? AND user_id=?");
                $stmt->bind_param("ii", $portfolioId, $userId);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($res) {
                    $slug = $res['slug'];

                    $deployPath = __DIR__ . "/public/portfolios/$slug";

                    deleteFolder($deployPath);
                    mkdir($deployPath, 0755, true);

                    // save zip temp
                    $zipPath = $deployPath . "/temp.zip";
                    move_uploaded_file($_FILES['zip']['tmp_name'], $zipPath);

                    require_once __DIR__ . '/engine/secure_unzip.php';

                    $result = secureExtractZip($zipPath, $deployPath);

                    unlink($zipPath);

                    if ($result['status']) {
                        $mysqli->query("UPDATE portfolios SET status='live' WHERE id=$portfolioId");
                        $messages[] = "Re-upload successful 🔄";
                    } else {
                        $errors[] = $result['error'];
                    }
                }
            }
        }
    }
}

/* ---------- FETCH PORTFOLIOS ---------- */
$stmt = $mysqli->prepare("
    SELECT id, slug, status 
    FROM portfolios 
    WHERE user_id=? 
    ORDER BY id DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$portfolios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$mysqli->close();

/* ---------- HELPERS ---------- */
function deleteFolder($dir) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $f) {
        if ($f != '.' && $f != '..') {
            $p = "$dir/$f";
            is_dir($p) ? deleteFolder($p) : unlink($p);
        }
    }
    rmdir($dir);
}

function badge($s) {
    return match($s) {
        'live' => '🟢 Live',
        'pending' => '🟡 Pending',
        'removed' => '🔴 Removed',
        default => $s
    };
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Portfolio</title>
    <style>
        body { font-family: Arial; background:#f5f7fb; padding:20px; }
        .card { background:white; padding:15px; margin-bottom:15px; border-radius:10px; }
        .btn { padding:6px 10px; border:none; border-radius:5px; cursor:pointer; }
        .green { background:#34d399; }
        .red { background:#f87171; }
        .blue { background:#60a5fa; }
    </style>
</head>
<body>

<h2>Portfolio Manager</h2>

<?php foreach ($errors as $e): ?>
<p style="color:red"><?= htmlspecialchars($e) ?></p>
<?php endforeach; ?>

<?php foreach ($messages as $m): ?>
<p style="color:green"><?= htmlspecialchars($m) ?></p>
<?php endforeach; ?>

<!-- Upload -->
<div class="card">
    <h3>Upload New</h3>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= student_csrf() ?>">
        <input type="hidden" name="action" value="upload">
        <input type="file" name="zip" required>
        <button class="btn green">Upload</button>
    </form>
</div>

<!-- Portfolios -->
<div class="card">
    <h3>Your Portfolios</h3>

    <table width="100%">
        <tr>
            <th>Slug</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>

        <?php foreach ($portfolios as $p): ?>
        <tr>
            <td><?= $p['slug'] ?></td>
            <td><?= badge($p['status']) ?></td>
            <td>

                <?php if ($p['status'] === 'live'): ?>
                    <a href="<?= $baseUrl . $p['slug'] ?>/" target="_blank" class="btn blue">Open</a>
                <?php endif; ?>

                <!-- DELETE -->
                <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= student_csrf() ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="portfolio_id" value="<?= $p['id'] ?>">
                    <button class="btn red">Delete</button>
                </form>

                <!-- REUPLOAD -->
                <form method="post" enctype="multipart/form-data" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= student_csrf() ?>">
                    <input type="hidden" name="action" value="reupload">
                    <input type="hidden" name="portfolio_id" value="<?= $p['id'] ?>">
                    <input type="file" name="zip" required>
                    <button class="btn green">Replace</button>
                </form>

            </td>
        </tr>
        <?php endforeach; ?>

    </table>
</div>

</body>
</html>