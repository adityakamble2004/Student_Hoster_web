<?php
// engine/publish_helper.php
declare(strict_types=1);

function validate_and_publish(string $tmpDir, string $slug, int $userId, mysqli $mysqli): array {
    // returns ['ok'=>bool, 'error'=>string|null, 'notes'=>string|null]
    $allowed = ['html','htm','css','js','png','jpg','jpeg','gif','webp','svg','json','map','woff','woff2','ttf','ico','txt'];
    // 1. check index
    $indexPath = null;
    foreach (['index.html','index.htm'] as $n) {
        if (is_file($tmpDir . DIRECTORY_SEPARATOR . $n)) { $indexPath = $tmpDir . DIRECTORY_SEPARATOR . $n; break; }
    }
    if (!$indexPath) return ['ok'=>false, 'error'=>'missing_index', 'notes'=>'No index.html found at root'];

    // 2. walk files and validate
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir));
    foreach ($it as $file) {
        if ($file->isDir()) continue;
        $name = $file->getFilename();
        $path = $file->getPathname();
        // path traversal check
        if (strpos($path, '..') !== false) return ['ok'=>false, 'error'=>'path_traversal', 'notes'=>$path];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === '') return ['ok'=>false, 'error'=>'no_extension', 'notes'=>$name];
        if (!in_array($ext, $allowed, true)) {
            return ['ok'=>false, 'error'=>'disallowed_extension', 'notes'=>$ext];
        }
        // server-side code check
        if (in_array($ext, ['html','htm','js'], true)) {
            $contents = file_get_contents($path);
            if (stripos($contents, '<?php') !== false) return ['ok'=>false, 'error'=>'php_code_found', 'notes'=>$name];
            if (preg_match('/<script[^>]*src=["\']?javascript:/i', $contents)) return ['ok'=>false, 'error'=>'javascript_url', 'notes'=>$name];
        }
    }

    // 3. run ClamAV scan (assumes clamscan available)
    $scanCmd = 'clamscan -r --no-summary ' . escapeshellarg($tmpDir);
    exec($scanCmd, $out, $rc);
    $scanReport = implode("\n", $out);
    if ($rc !== 0) {
        // infected or error
        $mysqli->query("UPDATE uploads SET scan_status='infected', scan_report='".$mysqli->real_escape_string($scanReport)."' WHERE stored_filename LIKE '%$slug%'");
        return ['ok'=>false, 'error'=>'infected', 'notes'=>$scanReport];
    }

    // 4. smoke test: ensure index has body content
    $indexHtml = file_get_contents($indexPath);
    if (stripos($indexHtml, '<body') === false || strlen(strip_tags($indexHtml)) < 20) {
        // flag but allow moderator review; here we fail to require manual check
        return ['ok'=>false, 'error'=>'empty_body', 'notes'=>'Index has little or no body content'];
    }

    // 5. publish: move to public/portfolios/{slug}/
    $dest = __DIR__ . '/../public/portfolios/' . $slug;
    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
    // ensure dest does not exist
    if (is_dir($dest)) shell_exec('rm -rf ' . escapeshellarg($dest));
    // move tmpDir to dest
    $mv = rename($tmpDir, $dest);
    if (!$mv) {
        // fallback to recursive copy
        $rcopy = recursive_copy($tmpDir, $dest);
        if (!$rcopy) return ['ok'=>false, 'error'=>'publish_failed', 'notes'=>'move and copy failed'];
        shell_exec('rm -rf ' . escapeshellarg($tmpDir));
    }

    // 6. create portfolio DB record
    $size = folder_size($dest);
    $title = 'Portfolio ' . $slug;
    $storagePath = 'public/portfolios/' . $slug . '/';
    $stmt = $mysqli->prepare("INSERT INTO portfolios (user_id, title, slug, storage_path, visibility, size_bytes, status, published_at, created_at) VALUES (?, ?, ?, ?, 'public', ?, 'live', NOW(), NOW())");
    $stmt->bind_param('isssi', $userId, $title, $slug, $storagePath, $size);
    $stmt->execute();
    $portfolioId = $stmt->insert_id;
    $stmt->close();

    return ['ok'=>true, 'error'=>null, 'notes'=>'published', 'portfolio_id'=>$portfolioId];
}

function folder_size(string $dir): int {
    $size = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $f) if ($f->isFile()) $size += $f->getSize();
    return $size;
}

function recursive_copy($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while(false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                if (!recursive_copy($src . '/' . $file, $dst . '/' . $file)) return false;
            } else {
                if (!copy($src . '/' . $file, $dst . '/' . $file)) return false;
            }
        }
    }
    closedir($dir);
    return true;
}
