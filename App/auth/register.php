<?php
// register.php
// Simple, secure registration form and handler using MySQLi and db_connect.php

declare(strict_types=1);
session_start();

require_once(__DIR__ . '/../DB/db_connect.php');// adjust path if db_connect.php is in project root

// CSRF helpers
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_token(): string {
    return $_SESSION['csrf_token'];
}
function verify_csrf(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// Helper to escape output
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$errors = [];
$success = null;

// If form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic CSRF check
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid request (CSRF token). Please try again.';
    }

    // Collect and trim inputs
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $password_confirm = (string)($_POST['password_confirm'] ?? '');
    $college = trim((string)($_POST['college'] ?? ''));
    $role = in_array($_POST['role'] ?? 'student', ['student','recruiter','moderator','admin'], true) ? $_POST['role'] : 'student';

    // Validation
    if ($name === '') $errors[] = 'Name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $password_confirm) $errors[] = 'Passwords do not match.';

    // If no validation errors, attempt to insert user
    if (empty($errors)) {
        try {
            $mysqli = db_connect();

            // Check if email already exists
            $stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = 'Email is already registered. Try logging in or use a different email.';
                $stmt->close();
            } else {
                $stmt->close();

                // Hash password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // Insert user
                $insert = $mysqli->prepare('INSERT INTO users (name, email, password_hash, role, college, is_verified, status, created_at) VALUES (?, ?, ?, ?, ?, 0, "active", NOW())');
                $insert->bind_param('sssss', $name, $email, $password_hash, $role, $college);
                $insert->execute();
                $insert->close();

                // Optionally: send verification email here (not implemented)
                $success = 'Registration successful. You can now log in.';
            }

            $mysqli->close();
        } catch (Throwable $ex) {
            error_log('Register error: ' . $ex->getMessage());
            $errors[] = 'An unexpected error occurred. Please try again later.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Register — StudentPort</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body{font-family:Inter,system-ui,Arial;margin:0;padding:24px;background:#f6f8fb;color:#0b1220}
    .card{max-width:520px;margin:28px auto;padding:20px;border-radius:10px;background:#fff;box-shadow:0 6px 20px rgba(10,20,40,0.06)}
    h1{margin:0 0 12px 0}
    label{display:block;margin-top:12px;font-weight:600}
    input[type="text"],input[type="email"],input[type="password"],select{width:100%;padding:10px;border:1px solid #dfe6ef;border-radius:8px;margin-top:6px}
    .row{display:flex;gap:8px}
    .btn{display:inline-block;padding:10px 14px;border-radius:8px;background:#34d399;color:#04263b;border:none;font-weight:700;cursor:pointer;margin-top:14px}
    .btn.secondary{background:transparent;border:1px solid #cbd5e1;color:#0b1220}
    .errors{background:#fff5f5;border:1px solid #f8d7da;color:#842029;padding:10px;border-radius:8px;margin-bottom:12px}
    .success{background:#ecfdf5;border:1px solid #bbf7d0;color:#064e3b;padding:10px;border-radius:8px;margin-bottom:12px}
    .small{font-size:0.9rem;color:#6b7280;margin-top:8px}
  </style>
</head>
<body>
  <div class="card" role="main">
    <h1>Create an account</h1>

    <?php if (!empty($errors)): ?>
      <div class="errors" role="alert">
        <ul style="margin:0 0 0 18px;padding:0">
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="success" role="status"><?= e($success) ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <label for="name">Full name</label>
      <input id="name" name="name" type="text" required value="<?= e($_POST['name'] ?? '') ?>">

      <label for="email">Email</label>
      <input id="email" name="email" type="email" required value="<?= e($_POST['email'] ?? '') ?>">

      <label for="college">College (optional)</label>
      <input id="college" name="college" type="text" value="<?= e($_POST['college'] ?? '') ?>">

      <label for="password">Password</label>
      <input id="password" name="password" type="password" required>

      <label for="password_confirm">Confirm password</label>
      <input id="password_confirm" name="password_confirm" type="password" required>

      <label for="role">Role</label>
      <select id="role" name="role">
        <option value="student" <?= (($_POST['role'] ?? '') === 'student') ? 'selected' : '' ?>>Student</option>
        <option value="recruiter" <?= (($_POST['role'] ?? '') === 'recruiter') ? 'selected' : '' ?>>Recruiter</option>
        <option value="moderator" <?= (($_POST['role'] ?? '') === 'moderator') ? 'selected' : '' ?>>Moderator</option>
        <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
      </select>

      <div style="display:flex;gap:8px;align-items:center">
        <button class="btn" type="submit">Create account</button>
        <a class="btn secondary" href="login.php">Already have an account?</a>
      </div>

      <div class="small">By creating an account you agree to our Terms and Privacy Policy.</div>
    </form>
  </div>
</body>
</html>
