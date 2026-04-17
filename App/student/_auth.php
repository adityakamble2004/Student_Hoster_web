<?php
// student/_auth.php
session_start();
require_once __DIR__ . '/../DB/db_connect.php';

// Student guard
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') {
    header('Location: /auth/login.php');
    exit;
}

// CSRF token
if (!isset($_SESSION['csrf_token_student'])) {
    $_SESSION['csrf_token_student'] = bin2hex(random_bytes(32));
}
function student_csrf(): string { return $_SESSION['csrf_token_student']; }
function verify_student_csrf(string $t): bool { return hash_equals($_SESSION['csrf_token_student'] ?? '', $t); }

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
