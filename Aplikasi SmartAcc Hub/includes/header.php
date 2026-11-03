<?php
/**
 * SmartAcc Hub - Header Layout Component
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$user = current_user();
$theme = $_COOKIE['smartacc_theme'] ?? 'dark';
$currentPage = $_GET['page'] ?? 'dashboard';

// Determine page display title
$pageTitles = [
    'dashboard' => 'Dashboard Keuangan',
    'topup' => 'Pesanan Top Up Game',
    'topup_products' => 'Kelola Game & Produk Diamond',
    'keuangan' => 'Kas Masuk & Kas Keluar',
    'deposit' => 'Deposit Saldo Supplier',
    'transfer' => 'Transfer Antar Rekening',
    'rekonsiliasi' => 'Rekonsiliasi Kas & Bank',
    'coa' => 'Bagan Akun (Chart of Accounts)',
    'jurnal' => 'Jurnal Umum (Double-Entry)',
    'buku_besar' => 'Buku Besar Akun',
    'neraca_saldo' => 'Neraca Saldo (Trial Balance)',
    'penyesuaian' => 'Jurnal Penyesuaian',
    'laba_rugi' => 'Laporan Laba Rugi',
    'neraca' => 'Laporan Posisi Keuangan (Neraca)',
    'arus_kas' => 'Laporan Arus Kas',
    'laporan_game' => 'Laporan Penjualan per Game',
    'laporan_transaksi' => 'Laporan Margin Transaksi',
    'pelanggan' => 'Data Pelanggan',
    'supplier' => 'Data Supplier Top Up',
    'utang_piutang' => 'Catatan Utang & Piutang',
    'profil' => 'Pengaturan Profil Usaha',
    'users' => 'Manajemen Pengguna & Hak Akses',
    'log' => 'Riwayat Aktivitas Pengguna',
    'backup' => 'Backup & Pemulihan Database'
];

$titleText = $pageTitles[$currentPage] ?? 'Sistem Keuangan Top Up Game';
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titleText) ?> - <?= APP_NAME ?></title>
    <!-- FontAwesome 6 Free CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom Style System -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
    <!-- Include Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Container -->
    <div class="app-main">
        <!-- Header Navbar -->
        <header class="app-header">
            <div class="header-left">
                <button type="button" class="btn btn-outline btn-sm d-lg-none" onclick="toggleSidebar()" style="display:none;" id="sidebarToggleBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="header-title-badge">
                    <span><?= htmlspecialchars($titleText) ?></span>
                </div>
            </div>

            <div class="header-right">
                <!-- Theme Toggle Button (Hitam / Putih) -->
                <button type="button" class="theme-toggle-btn" id="themeToggleBtn" title="Ganti Mode Warna">
                    <i class="fas <?= $theme === 'dark' ? 'fa-sun text-warning' : 'fa-moon' ?>"></i>
                    <span class="theme-label"><?= $theme === 'dark' ? 'Mode Terang' : 'Mode Gelap' ?></span>
                </button>

                <!-- Quick New Topup Order Button -->
                <?php if (has_role('admin', 'kasir')): ?>
                    <a href="<?= BASE_URL ?>/index.php?page=topup&action=new" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Transaksi Baru
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- App Content Area -->
        <main class="app-content">
            <!-- Flash Message Display -->
            <?= display_flash(); ?>
