<?php
// engine/worker.php
declare(strict_types=1);
require_once __DIR__ . '/../db_connect.php';

$redisAvailable = extension_loaded('redis');
$workDirBase = __DIR__ . '/tmp';
@mkdir($workDirBase, 0700, true);

function logmsg($s) { echo '['.date('Y-m-d H:i:s').'] '.$s.PHP_EOL; }

while (true) {
    $job = null;

    // Try Redis
    if ($redisAvailable) {
        try {
            $r = new Redis();
            $r->connect('127.0.0.1', 6379);
            $res = $r->brpop('upload_jobs', 5); // 5s timeout
            if ($res && isset($res[1])) {
                $job = json_decode($res[1], true);
            }
        } catch (Throwable $e) {
            logmsg('Redis error: '.$e->getMessage());
            $redisAvailable = false;
        }
    }

    // Fallback to job_queue table
    if ($job === null) {
        $mysqli = db_connect();
        $row = $mysqli->query("SELECT id, payload FROM job_queue WHERE processed = 0 ORDER BY created_at ASC LIMIT 1")->fetch_assoc() ?? null;
        if ($row) {
            $job = json_decode($row['payload'], true);
            // mark processed tentatively to avoid double-pick
            $mysqli->query("UPDATE job_queue SET processed = 1, processed_at = NOW() WHERE id = ".(int)$row['id']);
        }
        $mysqli->close();
    }

    if (!$job) {
        // no job, loop
        continue;
    }

    $uploadId = (int)($job['upload_id'] ?? 0);
    $userId = (int)($job['user_id'] ?? 0);
    $storedFilename = $job['stored_filename'] ?? '';

    if ($uploadId <= 0 || $storedFilename === '') {
        logmsg("Invalid job payload, skipping");
        continue;
    }

    $mysqli = db_connect();

    // Helper to update stage
    $updateStage = function(string $stage, ?string $detail = null) use ($mysqli, $uploadId) {
        $stmt = $mysqli->prepare('UPDATE uploads SET stage = ?, stage_detail = ?, attempts = attempts + 0 WHERE id = ?');
        $stmt->bind_param('ssi', $stage, $detail, $uploadId);
        $stmt->execute();
        $stmt->close();
    };

    // Start processing
    logmsg("Processing upload #$uploadId");
    $updateStage('unpacking', 'Preparing workspace');

    $tmpDir = $workDirBase . '/u' . $uploadId . '_' . bin2hex(random_bytes(6));
    if (!mkdir($tmpDir, 0700, true)) {
        logmsg("Failed to create tmp dir for $uploadId");
        $updateStage('error', 'Failed to create workspace');
        $mysqli->close();
        continue;
    }

    $uploadPath = __DIR__ . '/../uploads/' . $storedFilename;
    if (!is_file($uploadPath)) {
        logmsg("Stored file missing for $uploadId");
        $updateStage('error', 'Stored file missing');
        @rmdir($tmpDir);
        $mysqli->close();
        continue;
    }

    // Copy ZIP to tmp
    $tmpZip = $tmpDir . '/' . basename($storedFilename);
    if (!copy($uploadPath, $tmpZip)) {
        logmsg("Failed to copy zip for $uploadId");
        $updateStage('error', 'Failed to copy zip');
        shell_exec('rm -rf ' . escapeshellarg($tmpDir));
        $mysqli->close();
        continue;
    }

    // Unpack safely (basic unzip command; replace with robust library in prod)
    $updateStage('unpacking', 'Unpacking archive');
    $unzipCmd = sprintf('unzip -qq %s -d %s', escapeshellarg($tmpZip), escapeshellarg($tmpDir));
    exec($unzipCmd, $out, $rc);
    if ($rc !== 0) {
        logmsg("Unzip failed for $uploadId");
        $updateStage('error', 'Unzip failed');
        shell_exec('rm -rf ' . escapeshellarg($tmpDir));
        $mysqli->close();
        continue;
    }

    // Scanning (simulated)
    $updateStage('scanning', 'Running malware scan');
    logmsg("Simulating scan for $uploadId");
    // Simulate time for scan based on file size
    $size = filesize($uploadPath);
    $sleepSec = min(10, max(1, (int)($size / (1024*1024)))); // 1s per MB up to 10s
    sleep($sleepSec);

    // In a real implementation run clamscan and capture output:
    // $scanCmd = 'clamscan -r --no-summary ' . escapeshellarg($tmpDir);
    // exec($scanCmd, $scanOut, $scanRc);

    // For now assume clean
    $scanReport = "Simulated scan OK";
    $scanRc = 0;

    if ($scanRc !== 0) {
        logmsg("Scan detected issues for $uploadId");
        $stmt = $mysqli->prepare('UPDATE uploads SET stage = ?, stage_detail = ?, scan_report = ? WHERE id = ?');
        $s = 'infected'; $d = 'Malware detected'; $stmt->bind_param('sssi', $s, $d, $scanReport, $uploadId);
        $stmt->execute(); $stmt->close();
        // move to quarantine (not implemented here)
        shell_exec('rm -rf ' . escapeshellarg($tmpDir));
        $mysqli->close();
        continue;
    }

    // Validation
    $updateStage('validating', 'Validating file types and index.html');
    // Basic checks: index.html exists at root
    $indexFound = false;
    foreach (['index.html','index.htm'] as $n) {
        if (is_file($tmpDir . '/' . $n)) { $indexFound = true; break; }
    }
    if (!$indexFound) {
        logmsg("index.html missing for $uploadId");
        $stmt = $mysqli->prepare('UPDATE uploads SET stage = ?, stage_detail = ?, scan_report = ? WHERE id = ?');
        $s = 'needs_moderation'; $d = 'index.html missing'; $stmt->bind_param('sssi', $s, $d, $scanReport, $uploadId);
        $stmt->execute(); $stmt->close();
        shell_exec('rm -rf ' . escapeshellarg($tmpDir));
        $mysqli->close();
        continue;
    }

    // Check allowed extensions (simple)
    $allowed = ['html','htm','css','js','png','jpg','jpeg','gif','webp','svg','json','map','woff','woff2','ttf','ico','txt'];
    $bad = null;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir));
    foreach ($it as $file) {
        if ($file->isDir()) continue;
        $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        if ($ext === '') { $bad = 'file_without_extension'; break; }
        if (!in_array($ext, $allowed, true)) { $bad = 'disallowed_extension:'.$ext; break; }
    }
    if ($bad !== null) {
        logmsg("Validation failed ($bad) for $uploadId");
        $stmt = $mysqli->prepare('UPDATE uploads SET stage = ?, stage_detail = ?, scan_report = ? WHERE id = ?');
        $s = 'needs_moderation'; $d = $bad; $stmt->bind_param('sssi', $s, $d, $scanReport, $uploadId);
        $stmt->execute(); $stmt->close();
        shell_exec('rm -rf ' . escapeshellarg($tmpDir));
        $mysqli->close();
        continue;
    }

    // Publish (dev): move to public/portfolios/{slug}
    $updateStage('publishing', 'Publishing site');
    $slug = 'p' . $uploadId . '-' . bin2hex(random_bytes(4));
    $dest = __DIR__ . '/../public/portfolios/' . $slug;
    if (is_dir($dest)) shell_exec('rm -rf ' . escapeshellarg($dest));
    // move tmp dir to dest
    $moved = rename($tmpDir, $dest);
    if (!$moved) {
        // fallback to recursive copy
        $copyOk = shell_exec(sprintf('cp -r %s %s', escapeshellarg($tmpDir), escapeshellarg($dest))) !== null;
        if ($copyOk) shell_exec('rm -rf ' . escapeshellarg($tmpDir));
    }

    // Create portfolio record
    $sizeBytes = 0;
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dest));
    foreach ($rii as $f) { if ($f->isFile()) $sizeBytes += $f->getSize(); }

    $pstmt = $mysqli->prepare('INSERT INTO portfolios (user_id, title, slug, storage_path, visibility, size_bytes, status, published_at, created_at) VALUES (?, ?, ?, ?, "public", ?, "live", NOW(), NOW())');
    $title = 'Portfolio ' . $uploadId;
    $storagePath = 'public/portfolios/' . $slug . '/';
    $pstmt->bind_param('isssi', $userId, $title, $slug, $storagePath, $sizeBytes);
    $pstmt->execute();
    $portfolioId = $pstmt->insert_id;
    $pstmt->close();

    // Update uploads
    $ustmt = $mysqli->prepare('UPDATE uploads SET stage = ?, stage_detail = ?, scan_report = ?, portfolio_id = ?, processed_at = NOW() WHERE id = ?');
    $s = 'published'; $d = 'Published successfully';
    $ustmt->bind_param('sssii', $s, $d, $scanReport, $portfolioId, $uploadId);
    $ustmt->execute();
    $ustmt->close();

    $mysqli->query("INSERT INTO audit_logs (user_id, action, ip, created_at) VALUES ($userId, 'published_portfolio:$portfolioId', '127.0.0.1', NOW())");

    logmsg("Upload $uploadId published as portfolio $portfolioId (slug: $slug)");

    $mysqli->close();

    // loop continues to next job
}
