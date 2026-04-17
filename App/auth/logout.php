<?php 
declare(strict_types=1);

// common/logout.php

session_start();

// rest of code...
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

header("Location: ../index.html");
// Prevent further code execution after redirect
exit;
