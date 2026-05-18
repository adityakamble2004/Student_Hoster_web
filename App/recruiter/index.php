<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../DB/db_connect.php';

$mysqli  = db_connect();
$userId  = (int)$_SESSION['user_id'];
$name    = $_SESSION['user_name'] ?? 'Recruiter';

/* ---------- BASE URL ---------- */
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$baseUrl  = $protocol . $_SERVER['HTTP_HOST'] . '/Student%20portfolio%20hoster/App/student/public/portfolios/';

/* ---------- SEARCH / FILTER ---------- */
$search  = trim((string)($_GET['q']      ?? ''));
$college = trim((string)($_GET['college'] ?? ''));
$sort    = in_array($_GET['sort'] ?? '', ['newest','oldest','views'], true) ? $_GET['sort'] : 'newest';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

/* ---------- BUILD WHERE CLAUSE ---------- */
$wheres = ["p.status = 'live'", "p.visibility IN ('public','recruiter_only')"];
$params = [];
$types  = '';

if ($search !== '') {
    $wheres[] = "(u.name LIKE ? OR u.college LIKE ? OR p.slug LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= 'sss';
}

if ($college !== '') {
    $wheres[] = "u.college LIKE ?";
    $params[] = "%$college%";
    $types   .= 's';
}

$whereSQL = 'WHERE ' . implode(' AND ', $wheres);

$orderSQL = match($sort) {
    'oldest' => 'ORDER BY p.published_at ASC',
    'views'  => 'ORDER BY p.views_count DESC',
    default  => 'ORDER BY p.published_at DESC',
};

/* ---------- TOTAL COUNT ---------- */
$countSQL = "SELECT COUNT(*) FROM portfolios p JOIN users u ON p.user_id = u.id $whereSQL";
$stmt = $mysqli->prepare($countSQL);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_row()[0];
$stmt->close();
$totalPages = (int)ceil($total / $perPage);

/* ---------- FETCH PORTFOLIOS ---------- */
$sql = "
    SELECT
        p.id           AS portfolio_id,
        p.slug,
        p.storage_path,
        p.visibility,
        p.views_count,
        p.published_at,
        p.title,
        u.id           AS student_id,
        u.name         AS student_name,
        u.college,
        CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END AS shortlisted
    FROM portfolios p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN shortlists s ON s.portfolio_id = p.id AND s.recruiter_id = ?
    $whereSQL
    $orderSQL
    LIMIT ? OFFSET ?
";

$allTypes = 'i' . $types . 'ii';
$allParams = array_merge([$userId], $params, [$perPage, $offset]);

$stmt = $mysqli->prepare($sql);
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$portfolios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ---------- COLLEGE LIST FOR FILTER ---------- */
$colleges = $mysqli->query(
    "SELECT DISTINCT college FROM users WHERE role='student' AND college IS NOT NULL AND college != '' ORDER BY college"
)->fetch_all(MYSQLI_ASSOC);

/* ---------- STATS ---------- */
$totalLive       = (int)$mysqli->query("SELECT COUNT(*) FROM portfolios WHERE status='live'")->fetch_row()[0];
$totalShortlisted = (int)$mysqli->query("SELECT COUNT(*) FROM shortlists WHERE recruiter_id=$userId")->fetch_row()[0];
$totalStudents   = (int)$mysqli->query("SELECT COUNT(*) FROM users WHERE role='student' AND status='active'")->fetch_row()[0];

$mysqli->close();

/* ---------- AVATAR COLOR PALETTE ---------- */
$avatarColors = ['#58a6ff','#3fb950','#d29922','#bc8cff','#f78166','#56d364','#79c0ff','#ffa657'];
function avatarColor(string $name): string {
    global $avatarColors;
    return $avatarColors[abs(crc32($name)) % count($avatarColors)];
}
function initials(string $name): string {
    $parts = explode(' ', trim($name));
    return strtoupper(
        (mb_substr($parts[0] ?? '', 0, 1)) .
        (mb_substr($parts[1] ?? $parts[0] ?? '', 0, 1))
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Browse Portfolios — StudentPort Recruiter</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- ── NAVBAR ─────────────────────────────────────────── -->
<nav class="navbar">
    <a class="navbar-brand" href="index.php">
        <div class="logo">SP</div>
        StudentPort
    </a>
    <div class="navbar-nav">
        <a href="index.php"     class="nav-link active">Browse</a>
        <a href="shortlist.php" class="nav-link">Shortlist
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
            <div class="page-title">Student Portfolios</div>
            <div class="page-subtitle">Welcome back, <?= e($name) ?> — discover and shortlist talent</div>
        </div>
    </div>

    <!-- ── STATS BAR ────────────────────────────────── -->
    <div class="stats-bar">
        <div class="stat-pill">
            <div>
                <div class="num"><?= $totalLive ?></div>
                <div class="lbl">Live portfolios</div>
            </div>
        </div>
        <div class="stat-pill">
            <div>
                <div class="num"><?= $totalStudents ?></div>
                <div class="lbl">Active students</div>
            </div>
        </div>
        <div class="stat-pill">
            <div>
                <div class="num"><?= $totalShortlisted ?></div>
                <div class="lbl">Your shortlist</div>
            </div>
        </div>
    </div>

    <!-- ── SEARCH & FILTERS ─────────────────────────── -->
    <form method="get" action="" class="filters">
        <input
            type="text"
            name="q"
            class="search-box"
            placeholder="Search by name, college, slug..."
            value="<?= e($search) ?>"
        >
        <select name="college" class="filter-select">
            <option value="">All colleges</option>
            <?php foreach ($colleges as $c): ?>
                <option value="<?= e($c['college']) ?>" <?= ($college === $c['college']) ? 'selected' : '' ?>>
                    <?= e($c['college']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="sort" class="filter-select">
            <option value="newest" <?= ($sort === 'newest') ? 'selected' : '' ?>>Newest first</option>
            <option value="oldest" <?= ($sort === 'oldest') ? 'selected' : '' ?>>Oldest first</option>
            <option value="views"  <?= ($sort === 'views')  ? 'selected' : '' ?>>Most viewed</option>
        </select>
        <button class="btn btn-primary" type="submit">
            <svg viewBox="0 0 16 16" width="13" fill="currentColor"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/></svg>
            Search
        </button>
        <?php if ($search || $college): ?>
            <a href="index.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>

    <!-- ── RESULT COUNT ─────────────────────────────── -->
    <?php if ($total > 0): ?>
    <div style="font-size:13px;color:var(--muted);margin-bottom:16px">
        Showing <?= min($offset + 1, $total) ?>–<?= min($offset + $perPage, $total) ?> of <?= $total ?> portfolio<?= $total !== 1 ? 's' : '' ?>
    </div>
    <?php endif; ?>

    <!-- ── PORTFOLIO GRID ───────────────────────────── -->
    <div class="portfolio-grid">
        <?php if (empty($portfolios)): ?>
            <div class="empty-state">
                <div class="empty-icon">🎓</div>
                <h3>No portfolios found</h3>
                <p>Try adjusting your search or filters</p>
            </div>
        <?php endif; ?>

        <?php foreach ($portfolios as $p):
            $url    = $baseUrl . rawurlencode($p['slug']) . '/';
            $color  = avatarColor($p['student_name']);
            $init   = initials($p['student_name']);
            $since  = $p['published_at'] ? date('M j, Y', strtotime($p['published_at'])) : '—';
        ?>
        <div class="portfolio-card" data-portfolio-id="<?= (int)$p['portfolio_id'] ?>">

            <!-- iframe preview -->
            <div class="preview-wrap" onclick="window.open('<?= e($url) ?>','_blank')">
                <iframe
                    src="<?= e($url) ?>"
                    loading="lazy"
                    sandbox="allow-same-origin allow-scripts"
                    title="Portfolio preview of <?= e($p['student_name']) ?>"
                ></iframe>
                <div class="preview-overlay">
                    <span class="btn btn-primary" style="pointer-events:none;font-size:13px;">
                        <svg viewBox="0 0 16 16" width="13" fill="currentColor"><path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m11.293-3.293a1 1 0 0 1 0 1.414l-4 4a1 1 0 0 1-1.414-1.414l4-4a1 1 0 0 1 1.414 0"/></svg>
                        Open portfolio
                    </span>
                </div>
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
                    </div>
                    <?php if ($p['visibility'] === 'recruiter_only'): ?>
                        <span class="badge badge-private" style="margin-left:auto">Recruiter only</span>
                    <?php else: ?>
                        <span class="badge badge-live" style="margin-left:auto">Public</span>
                    <?php endif; ?>
                </div>

                <div class="meta-row">
                    <span class="meta-item">
                        <svg viewBox="0 0 16 16" width="12" fill="currentColor" style="opacity:.5"><path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m4.5-1.5a.5.5 0 0 0-1 0v5a.5.5 0 0 0 1 0V6.5zm2 0a.5.5 0 0 0-1 0v5a.5.5 0 0 0 1 0V6.5zm2 0a.5.5 0 0 0-1 0v5a.5.5 0 0 0 1 0V6.5zm2 0a.5.5 0 0 0-1 0v5a.5.5 0 0 0 1 0V6.5z"/></svg>
                        <?= number_format((int)$p['views_count']) ?> views
                    </span>
                    <span class="meta-item">
                        <svg viewBox="0 0 16 16" width="12" fill="currentColor" style="opacity:.5"><path d="M11 6.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm-3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm-5 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z"/></svg>
                        <?= e($since) ?>
                    </span>
                </div>
            </div>

            <!-- card actions -->
            <div class="card-actions">
                <a href="<?= e($url) ?>" target="_blank" class="btn" style="font-size:12px;padding:6px 10px;">
                    <svg viewBox="0 0 16 16" width="12" fill="currentColor"><path d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5"/><path d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0z"/></svg>
                    Open
                </a>

                <button
                    class="shortlist-btn <?= $p['shortlisted'] ? 'active' : '' ?>"
                    data-portfolio-id="<?= (int)$p['portfolio_id'] ?>"
                    data-student="<?= e($p['student_name']) ?>"
                    onclick="toggleShortlist(this)"
                    title="<?= $p['shortlisted'] ? 'Remove from shortlist' : 'Add to shortlist' ?>"
                >
                    <svg viewBox="0 0 16 16" fill="<?= $p['shortlisted'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="1.2" width="14" height="14">
                        <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.74.439L8 13.069l-5.26 2.87A.5.5 0 0 1 2 15.5z"/>
                    </svg>
                    <?= $p['shortlisted'] ? 'Saved' : 'Save' ?>
                </button>
            </div>

        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── PAGINATION ───────────────────────────────── -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php
        $qs = http_build_query(['q' => $search, 'college' => $college, 'sort' => $sort]);
        ?>
        <a href="?<?= $qs ?>&page=<?= $page - 1 ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">← Prev</a>
        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="?<?= $qs ?>&page=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="?<?= $qs ?>&page=<?= $page + 1 ?>" class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">Next →</a>
    </div>
    <?php endif; ?>

</div>

<!-- ── TOAST ──────────────────────────────────────────── -->
<div id="toast" class="toast"></div>

<script>
const CSRF = '<?= e(recruiter_csrf()) ?>';

async function toggleShortlist(btn) {
    const portfolioId = btn.dataset.portfolioId;
    const student     = btn.dataset.student;
    const isActive    = btn.classList.contains('active');

    btn.disabled = true;

    try {
        const res = await fetch('toggle_shortlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `csrf_token=${encodeURIComponent(CSRF)}&portfolio_id=${portfolioId}&action=${isActive ? 'remove' : 'add'}`
        });

        const data = await res.json();

        if (data.success) {
            btn.classList.toggle('active');
            const nowActive = btn.classList.contains('active');

            // Update icon fill
            const icon = btn.querySelector('svg');
            if (icon) icon.setAttribute('fill', nowActive ? 'currentColor' : 'none');

            // Update label
            btn.childNodes[btn.childNodes.length - 1].textContent = nowActive ? ' Saved' : ' Save';
            btn.title = nowActive ? 'Remove from shortlist' : 'Add to shortlist';

            showToast(
                nowActive ? `Saved ${student} to shortlist` : `Removed ${student} from shortlist`,
                nowActive ? 'success' : ''
            );
        } else {
            showToast(data.error || 'Something went wrong', 'error');
        }
    } catch (err) {
        showToast('Network error', 'error');
    } finally {
        btn.disabled = false;
    }
}

function showToast(msg, type = '') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show ' + type;
    setTimeout(() => { t.className = 'toast'; }, 3000);
}
</script>

</body>
</html>
