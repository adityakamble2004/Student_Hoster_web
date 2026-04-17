<?php
// student/preview.php
require_once __DIR__ . '/_auth.php';
$slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['slug'] ?? '');
$baseUrl = '/public/portfolios/' . $slug . '/';
$index = __DIR__ . '/../public/portfolios/' . $slug . '/index.html';
if (!is_file($index)) { http_response_code(404); echo "Preview not available"; exit; }

// Set strict CSP for the wrapper page
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none';");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Preview — <?= htmlspecialchars($slug) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>body{margin:0;background:#0b1220;color:#fff;display:flex;flex-direction:column;height:100vh}header{padding:12px;background:#071025}iframe{flex:1;border:0}</style>
</head>
<body>
  <header>
    <strong>Preview: <?= htmlspecialchars($slug) ?></strong>
    <span style="margin-left:12px;color:#9aa4b2">Sandboxed preview — scripts run but origin is isolated</span>
  </header>

  <iframe
    src="<?= htmlspecialchars($baseUrl . 'index.html') ?>"
    sandbox="allow-scripts allow-forms allow-popups"
    referrerpolicy="no-referrer"
    title="Portfolio preview">
  </iframe>
</body>
</html>
