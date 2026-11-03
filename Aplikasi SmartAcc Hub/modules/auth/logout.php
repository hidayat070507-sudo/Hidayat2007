<?php
/**
 * SmartAcc Hub - Logout Handler
 */

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

if (is_logged_in()) {
    log_activity('LOGOUT', "Pengguna " . ($_SESSION['username'] ?? '') . " keluar dari sistem");
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header('Location: ' . BASE_URL . '/index.php?page=login');
exit;
