<?php
// engine/worker.php
declare(strict_types=1);
require_once __DIR__ . '/../db_connect.php';

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$workDir = __DIR__ . '/tmp';
@mkdir($workDir, 0750, true);

while (true) {
    // Blocking pop with 5s timeout
    $job = $redis->brpop('upload_jobs', 5);
    if (!$job) { sleep(1); continue; }
    $payload = json_decode($job[1], true);
    if (!$payload || empty($payload['upload_id'])) continue;

    $uploadId = (int)$payload['upload_id'];
    $mysqli = db_connect();

    // Fetch upload record
    $stmt = $mysqli->prepare("SELECT stored_filename, user_id FROM uploads WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $uploadId);
    $stmt->execute();
    $stmt->bind_result($storedFilename, $userId);
    if (!$stmt->fetch()) { $stmt->close(); $mysqli->close(); continue; }
    $stmt->close();

    $tmpDir = $workDir . '/u' . $uploadId . '_' . bin2hex(random_bytes(6));
    mkdir($tmpDir, 0700, true);

    // 1. Copy or move ZIP to tmp and unzip
    $zipPath = __DIR__ . '/../uploads/' . basename($storedFilename);
    $unzipCmd = sprintf('unzip -qq %s -d %s', escapeshellarg($zipPath), escapeshellarg($tmpDir));
    exec($unzipCmd, $out, $rc);
    if ($rc !== 0) {
        // mark error
        $mysqli->query("UPDATE uploads SET scan_status='error', scan_report='unzip_failed' WHERE id=$uploadId");
        // cleanup
        shell_exec('rm -rf ' . escapeshellarg($tmpDir));
        $mysqli->close();
        continue;
    }

    // 2. Run ClamAV scan
    $scanCmd = 'clamscan -r --no-summary ' . escapeshellarg($tmpDir);
    exec($scanCmd, $scanOut, $scanRc);
    $scanReport = implode("\n", $scanOut);
    $scanStatus = ($scanRc === 0) ? 'clean' : 'infected';
    $mysqli->prepare("UPDATE uploads SET scan_status=?, scan_report=?, processed_at=NOW() WHERE id=?")
           ->bind_param('ssi', $scanStatus, $scanReport, $uploadId)
           ->execute();

    if ($scanStatus === 'infected') {
        // quarantine: mark portfolio or notify moderators
        $mysqli->query("INSERT INTO moderation_logs (portfolio_id, moderator_id, action, notes, created_at) VALUES (NULL, NULL, 'auto_quarantine', 'Infected upload: $uploadId', NOW())");
        shell_exec('rm -rf ' . escapeshellarg($tmpDir));
        $mysqli->close();
        continue;
    }

    // 3. Static validation: simple checks (no .php, no inline scripts)
    $bad = false;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir));
    foreach ($it as $file) {
        if ($file->isDir()) continue;
        $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        $allowed = ['html','htm','css','js','png','jpg','jpeg','gif','svg','webp','woff','woff2','ttf','json','map'];
        if (!in_array($ext, $allowed, true)) { $bad = 'disallowed_extension:'.$ext; break; }
        if (in_array($ext, ['html','htm','js'], true)) {
            $contents = file_get_contents($file->getPathname());
            if (stripos($contents, '<?php') !== false) { $bad = 'php_code_found'; break; }
            if (stripos($contents, '<script') !== false && stripos($contents, 'nonce-') === false) {
                // allow scripts only if you have a policy; here we flag inline scripts
                $bad = 'inline_script_found'; break;
            }
        }
    }

    if ($bad !== false) {
        $mysqli->query("UPDATE uploads SET scan_status='error', scan_report='".$mysqli->real_escape_string($bad)."' WHERE id=$uploadId");
        $mysqli->query("INSERT INTO moderation_logs (portfolio_id, moderator_id, action, notes, created_at) VALUES (NULL, NULL, 'validation_failed', '".$mysqli->real_escape_string($bad)."', NOW())");
        shell_exec('rm -rf ' . escapeshellarg($tmpDir));
        $mysqli->close();
        continue;
    }

    // 4. Move to object storage or publish directory
    // Example: move to /var/www/portfolios/{slug}/ (you should use S3 in prod)
    $slug = 'p' . $uploadId . '-' . bin2hex(random_bytes(4));
    $dest = __DIR__ . '/../public/portfolios/' . $slug;
    mkdir(dirname($dest), 0755, true);
    $moveCmd = sprintf('mv %s %s', escapeshellarg($tmpDir), escapeshellarg($dest));
    exec($moveCmd, $mout, $mrc);

    // 5. Create portfolio record
    $size = 0;
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dest));
    foreach ($rii as $f) { if ($f->isFile()) $size += $f->getSize(); }
    $stmt = $mysqli->prepare("INSERT INTO portfolios (user_id, title, slug, storage_path, visibility, size_bytes, status, published_at, created_at) VALUES (?, ?, ?, ?, 'public', ?, 'live', NOW(), NOW())");
    $title = 'Portfolio ' . $uploadId;
    $storagePath = 'public/portfolios/' . $slug . '/';
    $stmt->bind_param('isssi', $userId, $title, $slug, $storagePath, $size);
    $stmt->execute();
    $portfolioId = $stmt->insert_id;
    $stmt->close();

    // Link upload to portfolio
    $mysqli->query("UPDATE uploads SET portfolio_id=$portfolioId WHERE id=$uploadId");
    $mysqli->query("INSERT INTO audit_logs (user_id, action, ip, created_at) VALUES ($userId, 'published_portfolio:$portfolioId', '127.0.0.1', NOW())");

    // Cleanup and continue
    $mysqli->close();
    // sleep briefly to avoid tight loop; worker will block on brpop
}
