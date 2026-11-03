<?php
/**
 * SmartAcc Hub - Main Router / Front Controller
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/schema.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Auto-initialize SQLite database if not yet created
init_database();

// Get requested page
$page = $_GET['page'] ?? 'dashboard';

// Public routes
if ($page === 'login') {
    require __DIR__ . '/modules/auth/login.php';
    exit;
}

if ($page === 'logout') {
    require __DIR__ . '/modules/auth/logout.php';
    exit;
}

// Protected routes - require login
require_login();

// Route mapping
$routes = [
    // 1. Dashboard
    'dashboard'         => '/modules/dashboard/index.php',

    // 2. Top Up Game
    'topup'             => '/modules/topup/index.php',
    'topup_products'    => '/modules/topup/products.php',

    // 3. Manajemen Keuangan
    'keuangan'          => '/modules/keuangan/index.php',
    'deposit'           => '/modules/keuangan/deposit.php',
    'transfer'          => '/modules/keuangan/transfer.php',
    'rekonsiliasi'      => '/modules/keuangan/rekonsiliasi.php',

    // 4. Modul Akuntansi
    'coa'               => '/modules/akuntansi/coa.php',
    'jurnal'            => '/modules/akuntansi/jurnal.php',
    'buku_besar'        => '/modules/akuntansi/buku_besar.php',
    'neraca_saldo'      => '/modules/akuntansi/neraca_saldo.php',
    'penyesuaian'       => '/modules/akuntansi/penyesuaian.php',

    // 5. Laporan Keuangan
    'laba_rugi'         => '/modules/laporan/laba_rugi.php',
    'neraca'            => '/modules/laporan/neraca.php',
    'arus_kas'          => '/modules/laporan/arus_kas.php',
    'laporan_game'      => '/modules/laporan/per_game.php',
    'laporan_transaksi' => '/modules/laporan/transaksi.php',
    'export'            => '/modules/laporan/export.php',

    // 6. Kontak & Rekanan
    'pelanggan'         => '/modules/kontak/pelanggan.php',
    'supplier'          => '/modules/kontak/supplier.php',
    'utang_piutang'     => '/modules/kontak/utang_piutang.php',

    // 7. Pengaturan & Keamanan
    'profil'            => '/modules/pengaturan/profil.php',
    'users'             => '/modules/pengaturan/users.php',
    'log'               => '/modules/pengaturan/log.php',
    'backup'            => '/modules/pengaturan/backup.php'
];

if (isset($routes[$page])) {
    $targetFile = __DIR__ . $routes[$page];
    if (file_exists($targetFile)) {
        require $targetFile;
    } else {
        http_response_code(404);
        require __DIR__ . '/includes/header.php';
        echo '<div class="card-box text-center py-5">
            <h2 class="text-danger mb-2">404 - Halaman Belum Siap</h2>
            <p class="text-muted">Modul ' . htmlspecialchars($page) . ' sedang dalam tahap penyempurnaan.</p>
            <a href="' . BASE_URL . '/index.php?page=dashboard" class="btn btn-primary mt-3">Kembali ke Dashboard</a>
        </div>';
        require __DIR__ . '/includes/footer.php';
    }
} else {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}
