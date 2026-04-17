<?php
// admin/_auth.php
session_start();
require_once __DIR__ . '/../DB/db_connect.php';

// Simple admin guard
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function admin_csrf(): string { return $_SESSION['csrf_token']; }
function verify_admin_csrf(string $t): bool { return hash_equals($_SESSION['csrf_token'] ?? '', $t); }
