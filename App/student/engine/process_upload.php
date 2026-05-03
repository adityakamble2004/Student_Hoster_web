<?php
declare(strict_types=1);

require_once __DIR__ . '/../../DB/db_connect.php';
require_once __DIR__ . '/secure_unzip.php';

$mysqli = db_connect();

echo "🚀 Worker started...\n";

while (true) {

    // 🔹 1. Get next job
    $job = $mysqli->query("
        SELECT * FROM job_queue 
        WHERE processed = 0 
        ORDER BY id ASC 
        LIMIT 1
    ")->fetch_assoc();

    if (!$job) {
        sleep(2);
        continue;
    }

    $payload = json_decode($job['payload'], true);
    $uploadId = (int)$payload['upload_id'];

    echo "\n============================\n";
    echo "Processing upload ID: $uploadId\n";

    // 🔹 2. Get upload record
    $stmt = $mysqli->prepare("SELECT * FROM uploads WHERE id = ?");
    $stmt->bind_param("i", $uploadId);
    $stmt->execute();
    $upload = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$upload) {
        echo "Upload not found, skipping...\n";
        markProcessed($mysqli, (int)$job['id']);
        continue;
    }

    $userId = (int)$upload['user_id'];
    $portfolioId = null;
    $deployPath = null;

    try {

        // 🔹 3. Build ZIP path (FIXED ✅)
        $uploadsDir = realpath(__DIR__ . "/../../uploads");
        if (!$uploadsDir) {
            throw new Exception("Uploads directory not found");
        }

        $zipPath = $uploadsDir . "/" . $upload['stored_filename'];

        echo "ZIP PATH: $zipPath\n";

        if (!file_exists($zipPath)) {
            throw new Exception("ZIP file not found");
        }

        // 🔹 4. Create portfolio
        $slug = 'site_' . bin2hex(random_bytes(4));

        $stmt = $mysqli->prepare("
            INSERT INTO portfolios (user_id, slug, storage_path, status)
            VALUES (?, ?, '', 'pending')
        ");
        $stmt->bind_param("is", $userId, $slug);
        $stmt->execute();
        $portfolioId = $stmt->insert_id;
        $stmt->close();

        echo "Portfolio created: $portfolioId\n";

        // 🔹 5. Create deployment folder
        $deployPath = realpath(__DIR__ . "/../public/portfolios");

        if (!$deployPath) {
            throw new Exception("Base portfolio directory missing");
        }

        $deployPath = $deployPath . "/" . $slug;

        if (!mkdir($deployPath, 0755, true) && !is_dir($deployPath)) {
            throw new Exception("Failed to create deployment directory");
        }

        echo "Deploy path: $deployPath\n";

        // 🔹 6. Extract ZIP securely
        $result = secureExtractZip($zipPath, $deployPath);

        if (!$result['status']) {
            throw new Exception($result['error']);
        }

        echo "Extraction successful\n";

        // 🔹 7. Validate index.html exists
        if (!file_exists($deployPath . "/index.html")) {
            throw new Exception("index.html not found in ZIP");
        }

        // 🔹 8. Update portfolio (store WEB PATH, not server path)
        $publicPath = "/student/public/portfolios/" . $slug;

        $stmt = $mysqli->prepare("
            UPDATE portfolios 
            SET storage_path=?, status='live', published_at=NOW()
            WHERE id=?
        ");
        $stmt->bind_param("si", $publicPath, $portfolioId);
        $stmt->execute();
        $stmt->close();

        // 🔹 9. Link upload
        $stmt = $mysqli->prepare("
            UPDATE uploads 
            SET portfolio_id=?, scan_status='clean', processed_at=NOW()
            WHERE id=?
        ");
        $stmt->bind_param("ii", $portfolioId, $uploadId);
        $stmt->execute();
        $stmt->close();

        echo "✅ Deployment completed\n";

    } catch (Throwable $e) {

        echo "❌ Error: " . $e->getMessage() . "\n";

        // Cleanup deployment folder
        if ($deployPath && is_dir($deployPath)) {
            deleteFolder($deployPath);
        }

        // Mark portfolio removed if created
        if ($portfolioId) {
            $mysqli->query("UPDATE portfolios SET status='removed' WHERE id=$portfolioId");
        }

        // Update upload with error
        $stmt = $mysqli->prepare("
            UPDATE uploads 
            SET scan_status='error', scan_report=? 
            WHERE id=?
        ");
        $msg = $e->getMessage();
        $stmt->bind_param("si", $msg, $uploadId);
        $stmt->execute();
        $stmt->close();
    }

    // 🔹 10. Mark job processed
    markProcessed($mysqli, (int)$job['id']);
}

/* ---------- HELPERS ---------- */

function markProcessed($mysqli, int $jobId): void {
    $stmt = $mysqli->prepare("
        UPDATE job_queue 
        SET processed=1, processed_at=NOW() 
        WHERE id=?
    ");
    $stmt->bind_param("i", $jobId);
    $stmt->execute();
    $stmt->close();
}

function deleteFolder(string $dir): void {
    if (!is_dir($dir)) return;

    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $path = $dir . '/' . $file;
            is_dir($path) ? deleteFolder($path) : unlink($path);
        }
    }
    rmdir($dir);
}