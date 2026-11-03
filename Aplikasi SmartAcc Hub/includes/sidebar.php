<?php
/**
 * SmartAcc Hub - Sidebar Navigation Component
 */

require_once __DIR__ . '/auth.php';

$user = current_user();
$page = $_GET['page'] ?? 'dashboard';

function is_active(string $name, string $currentPage): string {
    return ($name === $currentPage) ? 'active' : '';
}
?>
<aside class="app-sidebar">
    <!-- Brand Title -->
    <div class="sidebar-header">
        <div class="sidebar-brand-icon">
            <i class="fas fa-gamepad"></i>
        </div>
        <div class="sidebar-brand-text">
            <h2>SmartAcc Hub</h2>
            <p>Akuntansi & Top Up Game</p>
        </div>
    </div>

    <!-- Navigation Links -->
    <div class="sidebar-nav">
        <!-- 1. DASHBOARD -->
        <a href="<?= BASE_URL ?>/index.php?page=dashboard" class="nav-item <?= is_active('dashboard', $page) ?>">
            <i class="fas fa-chart-pie"></i>
            <span>Dashboard</span>
        </a>

        <!-- 2. TOP UP GAME -->
        <div class="nav-section-title">Top Up Game</div>
        <a href="<?= BASE_URL ?>/index.php?page=topup" class="nav-item <?= is_active('topup', $page) ?>">
            <i class="fas fa-shopping-cart"></i>
            <span>Pesanan Pelanggan</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=topup_products" class="nav-item <?= is_active('topup_products', $page) ?>">
            <i class="fas fa-gem"></i>
            <span>Produk & Diamond</span>
        </a>

        <!-- 3. MANAJEMEN KEUANGAN -->
        <?php if (has_role('admin', 'keuangan', 'kasir')): ?>
        <div class="nav-section-title">Manajemen Keuangan</div>
        <a href="<?= BASE_URL ?>/index.php?page=keuangan" class="nav-item <?= is_active('keuangan', $page) ?>">
            <i class="fas fa-wallet"></i>
            <span>Kas Masuk & Keluar</span>
        </a>
        <?php endif; ?>

        <?php if (has_role('admin', 'keuangan')): ?>
        <a href="<?= BASE_URL ?>/index.php?page=deposit" class="nav-item <?= is_active('deposit', $page) ?>">
            <i class="fas fa-university"></i>
            <span>Deposit Supplier</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=transfer" class="nav-item <?= is_active('transfer', $page) ?>">
            <i class="fas fa-exchange-alt"></i>
            <span>Transfer Rekening</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=rekonsiliasi" class="nav-item <?= is_active('rekonsiliasi', $page) ?>">
            <i class="fas fa-check-double"></i>
            <span>Rekonsiliasi Saldo</span>
        </a>
        <?php endif; ?>

        <!-- 4. MODUL AKUNTANSI -->
        <?php if (has_role('admin', 'keuangan')): ?>
        <div class="nav-section-title">Modul Akuntansi</div>
        <a href="<?= BASE_URL ?>/index.php?page=coa" class="nav-item <?= is_active('coa', $page) ?>">
            <i class="fas fa-list-ol"></i>
            <span>Bagan Akun (COA)</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=jurnal" class="nav-item <?= is_active('jurnal', $page) ?>">
            <i class="fas fa-book"></i>
            <span>Jurnal Umum</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=buku_besar" class="nav-item <?= is_active('buku_besar', $page) ?>">
            <i class="fas fa-balance-scale"></i>
            <span>Buku Besar</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=neraca_saldo" class="nav-item <?= is_active('neraca_saldo', $page) ?>">
            <i class="fas fa-table"></i>
            <span>Neraca Saldo</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=penyesuaian" class="nav-item <?= is_active('penyesuaian', $page) ?>">
            <i class="fas fa-sliders-h"></i>
            <span>Jurnal Penyesuaian</span>
        </a>
        <?php endif; ?>

        <!-- 5. LAPORAN KEUANGAN -->
        <?php if (has_role('admin', 'keuangan')): ?>
        <div class="nav-section-title">Laporan Keuangan</div>
        <a href="<?= BASE_URL ?>/index.php?page=laba_rugi" class="nav-item <?= is_active('laba_rugi', $page) ?>">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Laporan Laba Rugi</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=neraca" class="nav-item <?= is_active('neraca', $page) ?>">
            <i class="fas fa-columns"></i>
            <span>Posisi Keuangan (Neraca)</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=arus_kas" class="nav-item <?= is_active('arus_kas', $page) ?>">
            <i class="fas fa-money-bill-wave"></i>
            <span>Laporan Arus Kas</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=laporan_game" class="nav-item <?= is_active('laporan_game', $page) ?>">
            <i class="fas fa-trophy"></i>
            <span>Penjualan per Game</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=laporan_transaksi" class="nav-item <?= is_active('laporan_transaksi', $page) ?>">
            <i class="fas fa-receipt"></i>
            <span>Keuntungan Transaksi</span>
        </a>
        <?php endif; ?>

        <!-- 6. PELANGGAN & SUPPLIER -->
        <div class="nav-section-title">Kontak & Rekanan</div>
        <a href="<?= BASE_URL ?>/index.php?page=pelanggan" class="nav-item <?= is_active('pelanggan', $page) ?>">
            <i class="fas fa-users"></i>
            <span>Data Pelanggan</span>
        </a>
        <?php if (has_role('admin', 'keuangan')): ?>
        <a href="<?= BASE_URL ?>/index.php?page=supplier" class="nav-item <?= is_active('supplier', $page) ?>">
            <i class="fas fa-truck-loading"></i>
            <span>Data Supplier</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=utang_piutang" class="nav-item <?= is_active('utang_piutang', $page) ?>">
            <i class="fas fa-hand-holding-usd"></i>
            <span>Catatan Utang & Piutang</span>
        </a>
        <?php endif; ?>

        <!-- 7. PENGATURAN & KEAMANAN -->
        <?php if (has_role('admin')): ?>
        <div class="nav-section-title">Pengaturan & Keamanan</div>
        <a href="<?= BASE_URL ?>/index.php?page=profil" class="nav-item <?= is_active('profil', $page) ?>">
            <i class="fas fa-store"></i>
            <span>Profil Usaha</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=users" class="nav-item <?= is_active('users', $page) ?>">
            <i class="fas fa-user-shield"></i>
            <span>Pengguna & Hak Akses</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=log" class="nav-item <?= is_active('log', $page) ?>">
            <i class="fas fa-history"></i>
            <span>Riwayat Aktivitas</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=backup" class="nav-item <?= is_active('backup', $page) ?>">
            <i class="fas fa-database"></i>
            <span>Backup & Pemulihan</span>
        </a>
        <?php endif; ?>
    </div>

    <!-- Sidebar Footer / User Profile -->
    <div class="sidebar-footer">
        <div class="user-card-mini">
            <div class="user-avatar-mini">
                <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="user-info-mini">
                <div class="user-name-mini"><?= htmlspecialchars($user['full_name'] ?? 'Pengguna') ?></div>
                <span class="user-role-badge"><?= htmlspecialchars($user['role'] ?? 'Guest') ?></span>
            </div>
            <a href="<?= BASE_URL ?>/index.php?page=logout" title="Keluar / Logout" style="color:#ef4444;font-size:1.1rem;padding:6px;">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</aside>
