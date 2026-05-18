<?php
// login.php
// Secure login form and handler using MySQLi and db_connect.php

declare(strict_types=1);
session_start();
require_once(__DIR__ . '/../DB/db_connect.php');

// CSRF helpers (reuse same pattern)
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_token(): string {
    return $_SESSION['csrf_token'];
}
function verify_csrf(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$errors = [];
$info = null;

// If already logged in, redirect to dashboard (adjust path)
if (!empty($_SESSION['user_id'])) {

    $role = $_SESSION['user_role'] ?? 'student';

    if ($role === 'student') {
        header('Location: ../student/');
    } elseif ($role === 'recruiter') {
        header('Location: ../recruiter/');
    } elseif ($role === 'moderator') {
        header('Location: ../moderator/');
    } else {
        header('Location: ../admin/');
    }

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid request (CSRF token).';
    }

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
    if ($password === '') $errors[] = 'Enter your password.';

    if (empty($errors)) {
        try {
            $mysqli = db_connect();

            $stmt = $mysqli->prepare('SELECT id, name, password_hash, role, is_verified, status FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->bind_result($id, $name, $password_hash, $role, $is_verified, $status);

            if ($stmt->fetch()) {
                // Check account status
                if ($status !== 'active') {
                    $errors[] = 'Account is not active. Contact support.';
                } elseif (!password_verify($password, $password_hash)) {
                    // Generic error to avoid user enumeration
                    $errors[] = 'Invalid credentials.';
                } else {
                    // Successful login
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$id;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_role'] = $role;
                    $_SESSION['is_verified'] = (int)$is_verified;

                    // Optional: remember me (simple implementation using cookie)
                    if ($remember) {
                        // Create a simple remember cookie (for production use a secure token table)
                        setcookie('remember_me', base64_encode($id . ':' . hash_hmac('sha256', $password_hash, 'your_secret_key')), time() + (86400 * 30), '/', '', false, true);
                    }

                    // Redirect based on role
                    if ($role === 'student') {
                        header('Location: ../student');
                    } elseif ($role === 'recruiter') {
                        header('Location: ../recruiter');
                    } elseif ($role === 'moderator') {
                        header('Location: ../moderator');
                    } else {
                        header('Location: ../admin');
                    }
                    exit;
                }
            } else {
                $errors[] = 'Invalid credentials.';
            }

            $stmt->close();
            $mysqli->close();
        } catch (Throwable $ex) {
            error_log('Login error: ' . $ex->getMessage());
            $errors[] = 'An unexpected error occurred. Please try again later.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Login — StudentPort</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body{font-family:Inter,system-ui,Arial;margin:0;padding:24px;background:#f6f8fb;color:#0b1220}
    .card{max-width:420px;margin:36px auto;padding:20px;border-radius:10px;background:#fff;box-shadow:0 6px 20px rgba(10,20,40,0.06)}
    h1{margin:0 0 12px 0}
    label{display:block;margin-top:12px;font-weight:600}
    input[type="email"],input[type="password"]{width:100%;padding:10px;border:1px solid #dfe6ef;border-radius:8px;margin-top:6px}
    .btn{display:inline-block;padding:10px 14px;border-radius:8px;background:#60a5fa;color:#04263b;border:none;font-weight:700;cursor:pointer;margin-top:14px}
    .btn.secondary{background:transparent;border:1px solid #cbd5e1;color:#0b1220}
    .errors{background:#fff5f5;border:1px solid #f8d7da;color:#842029;padding:10px;border-radius:8px;margin-bottom:12px}
    .small{font-size:0.9rem;color:#6b7280;margin-top:8px}
    .row{display:flex;justify-content:space-between;align-items:center;margin-top:8px}
  </style>
</head>
<body>
  <div class="card" role="main">
    <h1>Sign in</h1>

    <?php if (!empty($errors)): ?>
      <div class="errors" role="alert">
        <ul style="margin:0 0 0 18px;padding:0">
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" action="">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <label for="email">Email</label>
      <input id="email" name="email" type="email" required value="<?= e($_POST['email'] ?? '') ?>">

      <label for="password">Password</label>
      <input id="password" name="password" type="password" required>

      <div class="row">
        <label style="font-weight:600"><input type="checkbox" name="remember" <?= isset($_POST['remember']) ? 'checked' : '' ?>> Remember me</label>
        <a href="/auth/forgot_password.php" class="small">Forgot password?</a>
      </div>

      <div style="display:flex;gap:8px;align-items:center">
        <button class="btn" type="submit">Sign in</button>
        <a class="btn secondary" href="register.php">Create account</a>
      </div>
    </form>
  </div>
</body>
</html>
