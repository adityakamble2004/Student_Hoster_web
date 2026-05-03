<?php
$userName = $_SESSION['user_name'] ?? 'Student';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
body {
    margin:0;
    font-family: 'Segoe UI', Arial;
    background:#f5f7fb;
}

/* NAVBAR */
.navbar {
    background:#111827;
    color:white;
    padding:15px 25px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.navbar h2 {
    margin:0;
}

.navbar .right {
    display:flex;
    gap:15px;
    align-items:center;
}

.btn {
    padding:8px 12px;
    border:none;
    border-radius:6px;
    cursor:pointer;
}

.btn-logout {
    background:#ef4444;
    color:white;
}

/* CONTAINER */
.container {
    padding:20px;
}

/* CARDS */
.card {
    background:white;
    padding:15px;
    border-radius:10px;
    margin-bottom:20px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}

/* STATS */
.stats {
    display:flex;
    gap:20px;
    flex-wrap:wrap;
}

.stat-box {
    flex:1;
    min-width:150px;
    background:#fff;
    padding:15px;
    border-radius:10px;
    text-align:center;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}

/* TABLE */
table {
    width:100%;
    border-collapse:collapse;
}

th, td {
    padding:10px;
    border-bottom:1px solid #eee;
    text-align:left;
}

/* BADGES */
.badge {
    padding:5px 10px;
    border-radius:6px;
    font-size:12px;
}

.pending { background:#fef3c7; }
.live { background:#d1fae5; }
.error { background:#fee2e2; }

/* BUTTONS */
.btn-green { background:#10b981; color:white; }
.btn-blue { background:#3b82f6; color:white; }
.btn-red { background:#ef4444; color:white; }

</style>
</head>

<body>

<div class="navbar">
    <h2>🎓 Student Dashboard</h2>
    <div class="right">
        <span>👋 <?= htmlspecialchars($userName) ?></span>
        <a href="../auth/logout.php">
            <button class="btn btn-logout">Logout</button>
        </a>
    </div>
</div>

<div class="container">