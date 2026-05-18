<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../DB/db_connect.php';

$mysqli      = db_connect();
$userId      = (int)$_SESSION['user_id'];
$name        = $_SESSION['user_name'] ?? 'Recruiter';

/* ---------- BASE URL ---------- */
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$baseUrl  = $protocol . $_SERVER['HTTP_HOST'] . '/Student%20portfolio%20hoster/App/student/public/portfolios/';

/* ---------- HANDLE REMOVE (POST) ---------- */
$messages = [];
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_recruiter_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action      = $_POST['action']      ?? '';
        $portfolioId = (int)($_POST['portfolio_id'] ?? 0);

        if ($action === 'remove' && $portfolioId > 0) {
            $stmt = $mysqli->prepare("DELETE FROM shortlists WHERE recruiter_id=? AND portfolio_id=?");
            $stmt->bind_param('ii', $userId, $portfolioId);
            $stmt->execute();
            $stmt->close();
            $messages[] = 'Removed from shortlist.';
        }

        if ($action === 'clear_all') {
            $stmt = $mysqli->prepare("DELETE FROM shortlists WHERE recruiter_id=?");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
            $messages[] = 'Shortlist cleared.';
        }
    }
}

/* ---------- SEARCH / FILTER ---------- */
$search  = trim((string)($_GET['q']      ?? ''));
$college = trim((string)($_GET['college'] ?? ''));

$wheres = ["s.recruiter_id = ?"];
$params = [$userId];
$types  = 'i';

if ($search !== '') {
    $wheres[] = "(u.name LIKE ? OR u.college LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like]);
    $types .= 'ss';
}

if ($college !== '') {
    $wheres[] = "u.college LIKE ?";
    $params[] = "%$college%";
    $types   .= 's';
}

$whereSQL = 'WHERE ' . implode(' AND ', $wheres);

/* ---------- FETCH SHORTLIST ---------- */
$sql = "
    SELECT
        p.id           AS portfolio_id,
        p.slug,
        p.storage_path,
        p.visibility,
        p.views_count,
        p.published_at,
        p.status       AS portfolio_status,
        s.created_at   AS shortlisted_at,
        s.reason,
        u.id           AS student_id,
        u.name         AS student_name,
        u.email        AS student_email,
        u.college
    FROM shortlists s
    JOIN portfolios p ON s.portfolio_id = p.id
    JOIN users u      ON p.user_id = u.id
    $whereSQL
    ORDER BY s.created_at DESC
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$shortlist = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ---------- COLLEGE LIST FOR FILTER ---------- */
$colleges = $mysqli->query("
    SELECT DISTINCT u.college
    FROM shortlists s
    JOIN portfolios p ON s.portfolio_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE s.recruiter_id = $userId AND u.college IS NOT NULL AND u.college != ''
    ORDER BY u.college
")->fetch_all(MYSQLI_ASSOC);

$totalShortlisted = count($shortlist);

$mysqli->close();

/* ---------- HELPERS ---------- */
$avatarColors = ['#58a6ff','#3fb950','#d29922','#bc8cff','#f78166','#56d364','#79c0ff','#ffa657'];
function avatarColor(string $n): string { global $avatarColors; return $avatarColors[abs(crc32($n)) % count($avatarColors)]; }
function initials(string $n): string { $p = explode(' ', trim($n)); return strtoupper(mb_substr($p[0]??'',0,1).mb_substr($p[1]??$p[0]??'',0,1)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Shortlist — StudentPort Recruiter</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .shortlist-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: border-color .2s, transform .2s, box-shadow .2s;
        }
        .shortlist-card:hover {
            border-color: rgba(210,153,34,.4);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        .shortlist-card .preview-wrap { height: 180px; }
        .student-email {
            font-size: 12px;
            color: var(--accent);
            margin-top: 2px;
            word-break: break-all;
        }
        .shortlist-date {
            font-size: 11px;
            color: var(--muted);
            padding: 8px 16px;
            border-top: 1px solid var(--border);
            background: var(--surface2);
        }
        .removed-notice {
            font-size:11px;
            background: rgba(248,81,73,.08);
            color: var(--red);
            border-radius: 4px;
            padding: 2px 7px;
            margin-left: auto;
        }
    </style>
</head>
<body>

<!-- ── NAVBAR ─────────────────────────────────────────── -->
<nav class="navbar">
    <a class="navbar-brand" href="index.php">
        <div class="logo">SP</div>
        StudentPort
    </a>
    <div class="navbar-nav">
        <a href="index.php"     class="nav-link">Browse</a>
        <a href="shortlist.php" class="nav-link active">Shortlist
            <?php if ($totalShortlisted > 0): ?>
                <span style="background:rgba(210,153,34,.25);color:#d29922;font-size:10px;padding:1px 6px;border-radius:10px;font-weight:600;"><?= $totalShortlisted ?></span>
            <?php endif; ?>
        </a>
        <form method="post" action="../auth/logout.php" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= e(recruiter_csrf()) ?>">
            <button class="btn btn-ghost" type="submit">Logout</button>
        </form>
    </div>
</nav>

<div class="page-wrap">

    <!-- ── PAGE HEADER ──────────────────────────────── -->
    <div class="page-header">
        <div>
            <div class="page-title">My Shortlist</div>
            <div class="page-subtitle"><?= $totalShortlisted ?> candidate<?= $totalShortlisted !== 1 ? 's' : '' ?> saved</div>
        </div>
        <?php if ($totalShortlisted > 0): ?>
        <form method="post" onsubmit="return confirm('Clear your entire shortlist?')">
            <input type="hidden" name="csrf_token" value="<?= e(recruiter_csrf()) ?>">
            <input type="hidden" name="action"     value="clear_all">
            <button class="btn btn-danger" type="submit">Clear all</button>
        </form>
        <?php endif; ?>
    </div>

    <!-- ── FLASH MESSAGES ───────────────────────────── -->
    <?php foreach ($errors as $err): ?>
        <div style="background:rgba(248,81,73,.1);border:1px solid rgba(248,81,73,.3);color:var(--red);padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px;"><?= e($err) ?></div>
    <?php endforeach; ?>
    <?php foreach ($messages as $msg): ?>
        <div style="background:rgba(63,185,80,.1);border:1px solid rgba(63,185,80,.3);color:var(--green);padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px;"><?= e($msg) ?></div>
    <?php endforeach; ?>

    <!-- ── SEARCH & FILTERS ─────────────────────────── -->
    <?php if ($totalShortlisted > 0 || $search || $college): ?>
    <form method="get" action="" class="filters">
        <input
            type="text"
            name="q"
            class="search-box"
            placeholder="Search in shortlist..."
            value="<?= e($search) ?>"
        >
        <?php if (!empty($colleges)): ?>
        <select name="college" class="filter-select">
            <option value="">All colleges</option>
            <?php foreach ($colleges as $c): ?>
                <option value="<?= e($c['college']) ?>" <?= ($college === $c['college']) ? 'selected' : '' ?>>
                    <?= e($c['college']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit">Search</button>
        <?php if ($search || $college): ?>
            <a href="shortlist.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>
    <?php endif; ?>

    <!-- ── SHORTLIST GRID ───────────────────────────── -->
    <div class="portfolio-grid">
        <?php if (empty($shortlist)): ?>
            <div class="empty-state">
                <div class="empty-icon">🔖</div>
                <h3><?= ($search || $college) ? 'No matches found' : 'Your shortlist is empty' ?></h3>
                <p>
                    <?php if ($search || $college): ?>
                        Try different search terms.
                    <?php else: ?>
                        Browse portfolios and click <strong>Save</strong> to add candidates here.
                    <?php endif; ?>
                </p>
                <?php if (!$search && !$college): ?>
                    <a href="index.php" class="btn btn-primary" style="margin-top:16px;display:inline-flex;">Browse portfolios</a>
                <?php endif; ?>
            </div>

        <?php else: ?>
        <?php foreach ($shortlist as $p):
            $url      = $baseUrl . rawurlencode($p['slug']) . '/';
            $color    = avatarColor($p['student_name']);
            $init     = initials($p['student_name']);
            $since    = $p['published_at']   ? date('M j, Y', strtotime($p['published_at']))   : '—';
            $savedOn  = $p['shortlisted_at'] ? date('M j, Y', strtotime($p['shortlisted_at'])) : '—';
            $removed  = $p['portfolio_status'] !== 'live';
        ?>
        <div class="shortlist-card">

            <!-- iframe preview -->
            <div class="preview-wrap" onclick="<?= $removed ? '' : "window.open('" . e($url) . "','_blank')" ?>"
                 style="cursor:<?= $removed ? 'default' : 'pointer' ?>">
                <?php if (!$removed): ?>
                <iframe
                    src="<?= e($url) ?>"
                    loading="lazy"
                    sandbox="allow-same-origin allow-scripts"
                    title="Portfolio preview of <?= e($p['student_name']) ?>"
                    style="width:1280px;height:800px;border:none;transform:scale(0.234375);transform-origin:0 0;pointer-events:none;display:block;"
                ></iframe>
                <div class="preview-overlay">
                    <span class="btn btn-primary" style="pointer-events:none;font-size:13px;">Open portfolio</span>
                </div>
                <?php else: ?>
                <div style="height:100%;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:8px;color:var(--muted);">
                    <svg viewBox="0 0 16 16" width="32" fill="currentColor" style="opacity:.3"><path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg>
                    <span style="font-size:12px;">Portfolio removed</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- card body -->
            <div class="card-body">
                <div class="student-row">
                    <div class="avatar" style="background:<?= $color ?>20;color:<?= $color ?>;border:1.5px solid <?= $color ?>40;">
                        <?= $init ?>
                    </div>
                    <div class="student-info">
                        <div class="student-name"><?= e($p['student_name']) ?></div>
                        <div class="student-college"><?= e($p['college'] ?: 'College not specified') ?></div>
                        <div class="student-email"><?= e($p['student_email']) ?></div>
                    </div>
                    <?php if ($removed): ?>
                        <span class="removed-notice">Removed</span>
                    <?php else: ?>
                        <span class="badge badge-live" style="margin-left:auto">Live</span>
                    <?php endif; ?>
                </div>

                <div class="meta-row">
                    <span class="meta-item">
                        <svg viewBox="0 0 16 16" width="12" fill="currentColor" style="opacity:.5"><path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m4.5-1.5a.5.5 0 0 0-1 0v5a.5.5 0 0 0 1 0V6.5zm2 0a.5.5 0 0 0-1 0v5a.5.5 0 0 0 1 0V6.5zm2 0a.5.5 0 0 0-1 0v5a.5.5 0 0 0 1 0V6.5zm2 0a.5.5 0 0 0-1 0v5a.5.5 0 0 0 1 0V6.5z"/></svg>
                        <?= number_format((int)$p['views_count']) ?> views
                    </span>
                    <span class="meta-item">
                        <svg viewBox="0 0 16 16" width="12" fill="currentColor" style="opacity:.5"><path d="M11 6.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm-3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm-5 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z"/></svg>
                        Published <?= e($since) ?>
                    </span>
                </div>
            </div>

            <!-- saved date -->
            <div class="shortlist-date">
                Saved on <?= e($savedOn) ?>
            </div>

            <!-- card actions -->
            <div class="card-actions">
                <?php if (!$removed): ?>
                <a href="<?= e($url) ?>" target="_blank" class="btn" style="font-size:12px;padding:6px 10px;">
                    <svg viewBox="0 0 16 16" width="12" fill="currentColor"><path d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5"/><path d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0z"/></svg>
                    Open
                </a>
                <?php endif; ?>

                <form method="post" style="display:inline;margin-left:auto" onsubmit="return confirm('Remove from shortlist?')">
                    <input type="hidden" name="csrf_token"   value="<?= e(recruiter_csrf()) ?>">
                    <input type="hidden" name="action"       value="remove">
                    <input type="hidden" name="portfolio_id" value="<?= (int)$p['portfolio_id'] ?>">
                    <button class="btn btn-danger" type="submit" style="font-size:12px;padding:6px 10px;">
                        <svg viewBox="0 0 16 16" width="12" fill="currentColor"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>
                        Remove
                    </button>
                </form>
            </div>

        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<div id="toast" class="toast"></div>

<script>
function showToast(msg, type = '') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show ' + type;
    setTimeout(() => { t.className = 'toast'; }, 3000);
}
<?php if (!empty($messages)): ?>
window.addEventListener('DOMContentLoaded', () => {
    showToast('<?= e($messages[0]) ?>', 'success');
});
<?php endif; ?>
</script>

</body>
</html>
