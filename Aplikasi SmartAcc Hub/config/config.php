<?php
/**
 * SmartAcc Hub - Configuration File
 * Author: Antigravity AI
 */

// Error reporting for development
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set default timezone to Indonesia/Jakarta (WIB)
date_default_timezone_set('Asia/Jakarta');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base application paths
define('BASE_PATH', dirname(__DIR__));
define('DATABASE_PATH', BASE_PATH . '/database');

// Ensure database directory exists
if (!is_dir(DATABASE_PATH)) {
    mkdir(DATABASE_PATH, 0777, true);
}

// Database configuration
// Supported drivers: 'sqlite' or 'mysql'
define('DB_DRIVER', 'sqlite'); 

// SQLite configuration
define('SQLITE_FILE', DATABASE_PATH . '/smartacc.sqlite');

// MySQL configuration (for XAMPP/cPanel deployment if needed)
define('DB_HOST', 'localhost');
define('DB_NAME', 'smartacc_hub');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', '3306');

// Application Details
define('APP_NAME', 'SmartAcc Hub');
define('APP_TAGLINE', 'Sistem Akuntansi & Manajemen Top Up Game Terpadu');
define('APP_VERSION', '1.0.0');

// Base URL helper
function get_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost:8000';
    $scriptDir = isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : '';
    $scriptDir = str_replace('\\', '/', $scriptDir);
    if ($scriptDir === '/' || $scriptDir === '.') {
        $scriptDir = '';
    }
    return rtrim($protocol . $host . $scriptDir, '/');
}

define('BASE_URL', get_base_url());
