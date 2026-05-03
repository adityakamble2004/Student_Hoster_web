<?php
declare(strict_types=1);

function secureExtractZip(string $zipPath, string $destPath): array {

    if (!file_exists($zipPath)) {
        return ['status' => false, 'error' => 'ZIP file not found'];
    }

    $zip = new ZipArchive();

    if ($zip->open($zipPath) !== true) {
        return ['status' => false, 'error' => 'Cannot open ZIP'];
    }

    $allowedExtensions = ['html','css','js','png','jpg','jpeg','gif','svg','webp'];

    $totalSize = 0;
    $maxSize = 50 * 1024 * 1024; // 50MB extracted limit

    // 🔍 Validate all files BEFORE extracting
    for ($i = 0; $i < $zip->numFiles; $i++) {

        $entry = $zip->getNameIndex($i);

        // Normalize path
        $entry = str_replace('\\', '/', $entry);

        // ❌ Block absolute paths
        if (strpos($entry, '/') === 0) {
            return ['status' => false, 'error' => 'Absolute paths not allowed'];
        }

        // ❌ Block traversal
        if (preg_match('#(^|/)\.\.(/|$)#', $entry)) {
            return ['status' => false, 'error' => 'Path traversal detected'];
        }

        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));

        // ❌ Block unwanted file types
        if ($ext && !in_array($ext, $allowedExtensions)) {
            return ['status' => false, 'error' => "Blocked file type: $ext"];
        }

        // 📦 Size check (zip bomb protection)
        $stat = $zip->statIndex($i);
        $totalSize += $stat['size'];

        if ($totalSize > $maxSize) {
            return ['status' => false, 'error' => 'Extracted size too large'];
        }
    }

    // 📁 Extract
    if (!$zip->extractTo($destPath)) {
        return ['status' => false, 'error' => 'Extraction failed'];
    }

    $zip->close();

    // 🔥 FIX: Detect index.html (even in nested folder)
    $indexPath = findIndexFile($destPath);

    if (!$indexPath) {
        return ['status' => false, 'error' => 'index.html not found'];
    }

    // 🔄 If index is inside subfolder → move contents up
    if ($indexPath !== $destPath . '/index.html') {

        $dir = dirname($indexPath);

        moveContentsUp($dir, $destPath);

        
    }

    return ['status' => true];
}

/* ---------- HELPERS ---------- */

function findIndexFile(string $dir): ?string {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (strtolower($file->getFilename()) === 'index.html') {
            return $file->getPathname();
        }
    }

    return null;
}

function moveContentsUp(string $src, string $dest): void {
    $files = scandir($src);

    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            rename("$src/$file", "$dest/$file");
        }
    }
}

