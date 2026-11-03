<?php
/**
 * SmartAcc Hub - Login Page
 */

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Silakan masukkan username dan password.';
    } else {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Setup Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];

            log_activity('LOGIN', "User {$user['username']} ({$user['role']}) berhasil login");
            header('Location: ' . BASE_URL . '/index.php?page=dashboard');
            exit;
        } else {
            $error = 'Username atau password yang Anda masukkan salah.';
        }
    }
}

$theme = $_COOKIE['smartacc_theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .demo-roles {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px dashed var(--border-color);
        }
        .demo-btn-group {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 10px;
        }
        .demo-btn {
            background: var(--bg-card-subtle);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 8px 6px;
            font-size: 0.72rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            color: var(--text-primary);
        }
        .demo-btn:hover {
            border-color: var(--primary);
            background: var(--primary-light);
            color: var(--primary);
        }
    </style>
</head>
<body class="login-body">
    <div class="login-card">
        <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
            <button type="button" class="theme-toggle-btn" id="themeToggleBtn" title="Ganti Mode Tema">
                <i class="fas <?= $theme === 'dark' ? 'fa-sun text-warning' : 'fa-moon' ?>"></i>
                <span class="theme-label"><?= $theme === 'dark' ? 'Mode Terang' : 'Mode Gelap' ?></span>
            </button>
        </div>

        <div class="login-logo">
            <div class="login-logo-icon">
                <i class="fas fa-gamepad"></i>
            </div>
            <h2 style="font-size:1.45rem;font-weight:800;letter-spacing:-0.5px;"><?= APP_NAME ?></h2>
            <p style="font-size:0.82rem;color:var(--text-muted);margin-top:4px;">Sistem Akuntansi & Manajemen Top Up Game</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <div style="position:relative;">
                    <input type="text" id="username" name="username" class="form-control" placeholder="Contoh: admin" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:10px;padding:12px;">
                <i class="fas fa-sign-in-alt"></i> Masuk ke Sistem
            </button>
        </form>

        <!-- Quick Demo Login Helpers -->
        <div class="demo-roles">
            <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);text-align:center;">
                Akun Demo Cepat (Klik untuk Isi):
            </div>
            <div class="demo-btn-group">
                <button type="button" class="demo-btn" onclick="fillCredentials('admin', 'admin123')">
                    <strong>Admin</strong><br><span style="font-size:0.65rem;color:var(--text-muted);">Akses Penuh</span>
                </button>
                <button type="button" class="demo-btn" onclick="fillCredentials('kasir', 'kasir123')">
                    <strong>Kasir</strong><br><span style="font-size:0.65rem;color:var(--text-muted);">Topup & Kas</span>
                </button>
                <button type="button" class="demo-btn" onclick="fillCredentials('keuangan', 'keuangan123')">
                    <strong>Keuangan</strong><br><span style="font-size:0.65rem;color:var(--text-muted);">Akuntansi</span>
                </button>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
    <script>
        function fillCredentials(u, p) {
            document.getElementById('username').value = u;
            document.getElementById('password').value = p;
        }
    </script>
</body>
</html>
