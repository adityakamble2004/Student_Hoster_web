<?php
// moderator/_auth.php
session_start();

require_once __DIR__ . '/../DB/db_connect.php';

// Moderator guard
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'moderator') {
    header('Location: /auth/login.php');
    exit;
}

// CSRF token
if (!isset($_SESSION['csrf_token_moderator'])) {
    $_SESSION['csrf_token_moderator'] = bin2hex(random_bytes(32));
}
function mod_csrf(): string { return $_SESSION['csrf_token_moderator']; }
function verify_mod_csrf(string $t): bool { return hash_equals($_SESSION['csrf_token_moderator'] ?? '', $t); }

// Simple output escaper
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
