<?php
require_once __DIR__ . '/../includes/authentification.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fallback if $BASE_URL is not defined by includes
if (!isset($BASE_URL) || !$BASE_URL) {
    // If script path is like /stage_platform/authentification/login.php => BASE_URL becomes /stage_platform
    $BASE_URL = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
}
// authentification/logout.php

// Clear all session data
$_SESSION = [];

// Delete session cookie (more secure)
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

// Destroy session
session_destroy();

// Redirect to login (or index)
header("Location: " . $BASE_URL . "/authentification/login.php");
exit;