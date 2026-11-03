<?php
/**
 * SmartAcc Hub - Authentication & Authorization Helper
 */

require_once dirname(__DIR__) . '/config/config.php';

function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role' => $_SESSION['role'],
        'email' => $_SESSION['email'] ?? ''
    ];
}

function has_role(...$roles): bool {
    if (!is_logged_in()) {
        return false;
    }
    $userRole = $_SESSION['role'] ?? '';
    // Admin has access to everything
    if ($userRole === 'admin') {
        return true;
    }
    return in_array($userRole, $roles, true);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/index.php?page=login');
        exit;
    }
}

function require_role(...$roles): void {
    require_login();
    if (!has_role(...$roles)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;max-width:500px;margin:50px auto;padding:24px;border-radius:12px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;text-align:center;">
            <h2>403 - Akses Ditolak</h2>
            <p>Anda tidak memiliki izin (hak akses) untuk membuka halaman ini.</p>
            <p><a href="' . BASE_URL . '/index.php?page=dashboard" style="color:#2563eb;text-decoration:none;font-weight:600;">&larr; Kembali ke Dashboard</a></p>
        </div>');
    }
}

// CSRF Helpers
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
