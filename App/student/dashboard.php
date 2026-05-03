<?php
$mysqli = db_connect();
$userId = (int)$_SESSION['user_id'];

$errors = [];
$messages = [];

/* ---------- DELETE PORTFOLIO ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_portfolio') {

    if (!verify_student_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {

        $portfolioId = (int)($_POST['portfolio_id'] ?? 0);

        // Check ownership
        $stmt = $mysqli->prepare("SELECT slug FROM portfolios WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $portfolioId, $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            $errors[] = 'Portfolio not found or access denied.';
        } else {

            $slug = $result['slug'];

            try {
                // Delete folder
                $folderPath = __DIR__ . "/public/portfolios/" . $slug;

                if (is_dir($folderPath)) {
                    deleteFolder($folderPath);
                }

                // Delete DB record
                $stmt = $mysqli->prepare("DELETE FROM portfolios WHERE id=?");
                $stmt->bind_param("i", $portfolioId);
                $stmt->execute();
                $stmt->close();

                // Unlink uploads
                $mysqli->query("UPDATE uploads SET portfolio_id=NULL WHERE portfolio_id=$portfolioId");

                // Audit log
                $stmt = $mysqli->prepare("
                    INSERT INTO audit_logs (user_id, action, ip, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $action = "portfolio_deleted:$portfolioId";
                $ip = $_SERVER['REMOTE_ADDR'];
                $stmt->bind_param("iss", $userId, $action, $ip);
                $stmt->execute();
                $stmt->close();

                $messages[] = "Portfolio deleted successfully ✅";

            } catch (Throwable $e) {
                $errors[] = "Delete failed: " . $e->getMessage();
            }
        }
    }
}

/* ---------- FETCH STATS ---------- */
$totalUploads = $mysqli->query("SELECT COUNT(*) as c FROM uploads WHERE user_id=$userId")->fetch_assoc()['c'];
$totalLive = $mysqli->query("SELECT COUNT(*) as c FROM portfolios WHERE user_id=$userId AND status='live'")->fetch_assoc()['c'];
$totalPending = $mysqli->query("SELECT COUNT(*) as c FROM uploads WHERE user_id=$userId AND scan_status='pending'")->fetch_assoc()['c'];

/* ---------- FETCH DATA ---------- */
$uploads = $mysqli->query("SELECT * FROM uploads WHERE user_id=$userId ORDER BY uploaded_at DESC")->fetch_all(MYSQLI_ASSOC);
$portfolios = $mysqli->query("SELECT * FROM portfolios WHERE user_id=$userId ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

/* ---------- BASE URL FIX ---------- */
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$projectPath = "/Student%20portfolio%20hoster/App/student/public/portfolios/";
$baseUrl = $protocol . $host . $projectPath;
?>

<!-- 🔷 MESSAGES -->
<?php foreach ($errors as $e): ?>
<div class="error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<?php foreach ($messages as $m): ?>
<div class="success"><?= htmlspecialchars($m) ?></div>
<?php endforeach; ?>

<!-- 🔷 STATS -->
<div class="stats">
    <div class="stat-box">
        <h3><?= $totalUploads ?></h3>
        <p>Total Uploads</p>
    </div>
    <div class="stat-box">
        <h3><?= $totalLive ?></h3>
        <p>Live Portfolios</p>
    </div>
    <div class="stat-box">
        <h3><?= $totalPending ?></h3>
        <p>Pending</p>
    </div>
</div>

<!-- 🔷 UPLOAD -->
<div class="card">
    <h3>Upload Portfolio</h3>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(student_csrf()) ?>">
        <input type="hidden" name="action" value="upload">
        <input type="file" name="zip" required>
        <button class="btn btn-green">Upload</button>
    </form>
</div>

<!-- 🔷 UPLOAD TABLE -->
<div class="card">
    <h3>Uploads</h3>
    <table>
        <tr>
            <th>File</th>
            <th>Status</th>
            <th>Date</th>
        </tr>

        <?php foreach ($uploads as $u): ?>
        <tr>
            <td><?= htmlspecialchars($u['original_filename']) ?></td>
            <td><?= htmlspecialchars($u['scan_status']) ?></td>
            <td><?= $u['uploaded_at'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<!-- 🔷 PORTFOLIOS -->
<div class="card">
    <h3>Portfolios</h3>
    <table>
        <tr>
            <th>Slug</th>
            <th>Status</th>
            <th>Action</th>
        </tr>

        <?php foreach ($portfolios as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['slug']) ?></td>
            <td>
                <span class="badge <?= $p['status'] ?>">
                    <?= $p['status'] ?>
                </span>
            </td>
            <td>
                <?php if ($p['status'] === 'live'): ?>

                    <!-- OPEN -->
                    <a href="<?= $baseUrl . urlencode($p['slug']) ?>/" target="_blank">
                        <button class="btn btn-blue">Open</button>
                    </a>

                    <!-- DELETE -->
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="action" value="delete_portfolio">
                        <input type="hidden" name="portfolio_id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(student_csrf()) ?>">
                        <button class="btn btn-red" onclick="return confirm('Delete this portfolio?')">
                            Delete
                        </button>
                    </form>

                <?php else: ?>
                    Processing...
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php
/* ---------- HELPER ---------- */
function deleteFolder($dir) {
    if (!is_dir($dir)) return;

    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $path = $dir . '/' . $file;
            is_dir($path) ? deleteFolder($path) : unlink($path);
        }
    }
    rmdir($dir);
}
?>