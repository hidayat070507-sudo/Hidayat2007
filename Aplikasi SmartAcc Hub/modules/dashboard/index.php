<?php
/**
 * SmartAcc Hub - Dashboard Module
 */

require_once dirname(dirname(__DIR__)) . '/includes/header.php';

$db = Database::getConnection();

// 1. Calculate Total Saldo Kas & Bank
$cashBankInfo = get_cash_and_bank_balances($db);
$totalKasBank = $cashBankInfo['total'];

// 2. Calculate Total Omzet, Modal, and Keuntungan (Status: BERHASIL)
$stmtSales = $db->query("SELECT 
    COALESCE(SUM(sell_price), 0) as total_omzet,
    COALESCE(SUM(modal_price), 0) as total_modal,
    COALESCE(SUM(profit), 0) as total_keuntungan,
    COUNT(*) as total_transaksi
    FROM topup_orders WHERE order_status = 'BERHASIL'");
$salesData = $stmtSales->fetch();

// 3. Transaksi Hari Ini
$today = date('Y-m-d');
$stmtToday = $db->prepare("SELECT 
    COUNT(*) as count_today,
    COALESCE(SUM(sell_price), 0) as omzet_today,
    COALESCE(SUM(profit), 0) as profit_today
    FROM topup_orders WHERE DATE(created_at) = ? AND order_status = 'BERHASIL'");
$stmtToday->execute([$today]);
$todayData = $stmtToday->fetch();

// 4. Pending / Processing Orders Notification
$stmtPending = $db->query("SELECT COUNT(*) FROM topup_orders WHERE order_status IN ('PENDING', 'DIPROSES')");
$pendingCount = (int)$stmtPending->fetchColumn();

// 5. Supplier Saldo Warning (low balance < 1.000.000)
$stmtLowSup = $db->query("SELECT name, balance FROM suppliers WHERE balance < 1000000");
$lowSuppliers = $stmtLowSup->fetchAll();

// 6. Recent Orders
$stmtRecent = $db->query("SELECT * FROM topup_orders ORDER BY id DESC LIMIT 6");
$recentOrders = $stmtRecent->fetchAll();

// 7. Data for 7-Day Revenue & Expense Chart
$chartDates = [];
$chartRevenue = [];
$chartExpense = [];

for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chartDates[] = date('d M', strtotime($d));

    // Sales Revenue for date d
    $sQ = $db->prepare("SELECT COALESCE(SUM(sell_price), 0) FROM topup_orders WHERE DATE(created_at) = ? AND order_status = 'BERHASIL'");
    $sQ->execute([$d]);
    $chartRevenue[] = (float)$sQ->fetchColumn();

    // Expense for date d
    $eQ = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM cash_transactions WHERE trans_date = ? AND trans_type = 'KAS_KELUAR'");
    $eQ->execute([$d]);
    $chartExpense[] = (float)$eQ->fetchColumn();
}
?>

<!-- Alert & Notifications -->
<?php if ($pendingCount > 0): ?>
    <div class="alert alert-warning" style="margin-bottom:20px;">
        <i class="fas fa-bell me-2"></i> 
        Ada <strong><?= $pendingCount ?> pesanan topup</strong> yang membutuhkan tindakan (status Pending / Diproses).
        <a href="<?= BASE_URL ?>/index.php?page=topup" style="margin-left:12px;font-weight:700;text-decoration:underline;color:inherit;">Lihat Pesanan &rarr;</a>
    </div>
<?php endif; ?>

<?php if (!empty($lowSuppliers)): ?>
    <div class="alert alert-info" style="margin-bottom:20px;">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Perhatian Saldo Supplier:</strong> 
        <?php foreach ($lowSuppliers as $sup): ?>
            <?= htmlspecialchars($sup['name']) ?> tersisa <?= format_rupiah($sup['balance']) ?>. 
        <?php endforeach; ?>
        <a href="<?= BASE_URL ?>/index.php?page=deposit" style="margin-left:10px;font-weight:700;text-decoration:underline;color:inherit;">Isi Deposit Sekarang &rarr;</a>
    </div>
<?php endif; ?>

<!-- Summary Stat Cards -->
<div class="stat-grid">
    <!-- Stat 1: Saldo Kas & Bank -->
    <div class="stat-card cyan">
        <div class="stat-info">
            <div class="stat-label">Total Saldo Kas & Bank</div>
            <div class="stat-value"><?= format_rupiah($totalKasBank) ?></div>
            <div class="stat-desc">
                <?php foreach ($cashBankInfo['accounts'] as $acc): ?>
                    <span style="display:inline-block;margin-right:8px;font-size:0.72rem;">
                        <?= htmlspecialchars($acc['name']) ?>: <strong><?= format_rupiah($acc['balance']) ?></strong>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="stat-icon cyan">
            <i class="fas fa-wallet"></i>
        </div>
    </div>

    <!-- Stat 2: Total Omzet Penjualan -->
    <div class="stat-card indigo">
        <div class="stat-info">
            <div class="stat-label">Total Omzet Penjualan</div>
            <div class="stat-value"><?= format_rupiah($salesData['total_omzet']) ?></div>
            <div class="stat-desc">Hari ini: <strong><?= format_rupiah($todayData['omzet_today']) ?></strong></div>
        </div>
        <div class="stat-icon indigo">
            <i class="fas fa-chart-line"></i>
        </div>
    </div>

    <!-- Stat 3: Modal & Keuntungan -->
    <div class="stat-card emerald">
        <div class="stat-info">
            <div class="stat-label">Total Keuntungan Bersih</div>
            <div class="stat-value" style="color:var(--success);"><?= format_rupiah($salesData['total_keuntungan']) ?></div>
            <div class="stat-desc">Modal Terpakai: <strong><?= format_rupiah($salesData['total_modal']) ?></strong></div>
        </div>
        <div class="stat-icon emerald">
            <i class="fas fa-coins"></i>
        </div>
    </div>

    <!-- Stat 4: Transaksi Hari Ini -->
    <div class="stat-card amber">
        <div class="stat-info">
            <div class="stat-label">Transaksi Hari Ini</div>
            <div class="stat-value"><?= (int)$todayData['count_today'] ?> Transaksi</div>
            <div class="stat-desc">Laba Hari Ini: <strong><?= format_rupiah($todayData['profit_today']) ?></strong></div>
        </div>
        <div class="stat-icon amber">
            <i class="fas fa-receipt"></i>
        </div>
    </div>
</div>

<!-- Main Row: Chart & Recent Orders -->
<div style="display:grid;grid-template-columns: 1.6fr 1fr;gap:24px;margin-bottom:24px;">
    <!-- Chart Box -->
    <div class="card-box" style="margin-bottom:0;">
        <div class="card-box-header">
            <div class="card-box-title">
                <i class="fas fa-chart-area" style="color:var(--primary);"></i>
                Grafik Pemasukan & Pengeluaran (7 Hari Terakhir)
            </div>
            <span style="font-size:0.75rem;color:var(--text-muted);font-weight:600;">Tren Keuangan</span>
        </div>
        <div style="position:relative;height:320px;width:100%;">
            <canvas id="financeTrendChart"></canvas>
        </div>
    </div>

    <!-- Quick Actions & Saldo Supplier Widget -->
    <div class="card-box" style="margin-bottom:0;display:flex;flex-direction:column;justify-content:space-between;">
        <div>
            <div class="card-box-header">
                <div class="card-box-title">
                    <i class="fas fa-server" style="color:var(--warning);"></i>
                    Saldo Server Supplier
                </div>
                <a href="<?= BASE_URL ?>/index.php?page=deposit" class="btn btn-outline btn-sm">Tambah</a>
            </div>
            <?php
            $stmtSups = $db->query("SELECT * FROM suppliers ORDER BY id ASC");
            $suppliers = $stmtSups->fetchAll();
            ?>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <?php foreach ($suppliers as $s): ?>
                    <div style="padding:12px 14px;background:var(--bg-card-subtle);border-radius:var(--radius-md);border:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <div style="font-weight:700;font-size:0.9rem;"><?= htmlspecialchars($s['name']) ?></div>
                            <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($s['phone'] ?: 'No Kontak') ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:1.05rem;font-weight:800;color:var(--primary);font-family:'JetBrains Mono', monospace;">
                                <?= format_rupiah($s['balance']) ?>
                            </div>
                            <span class="status-badge <?= $s['balance'] < 1000000 ? 'badge-warning' : 'badge-success' ?>" style="font-size:0.65rem;padding:2px 6px;">
                                <?= $s['balance'] < 1000000 ? 'Menipis' : 'Aman' ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border-color);">
            <div style="font-weight:700;font-size:0.85rem;margin-bottom:10px;color:var(--text-muted);text-transform:uppercase;">Akses Cepat:</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <a href="<?= BASE_URL ?>/index.php?page=topup&action=new" class="btn btn-primary btn-sm" style="width:100%;">
                    <i class="fas fa-plus-circle"></i> Input Topup
                </a>
                <a href="<?= BASE_URL ?>/index.php?page=keuangan&action=keluar" class="btn btn-danger btn-sm" style="width:100%;">
                    <i class="fas fa-minus-circle"></i> Catat Biaya
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions Table -->
<div class="card-box">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-history" style="color:var(--info);"></i>
            Transaksi Pesanan Terbaru
        </div>
        <a href="<?= BASE_URL ?>/index.php?page=topup" class="btn btn-outline btn-sm">Lihat Semua Pesanan &rarr;</a>
    </div>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>No Invoice</th>
                    <th>Waktu</th>
                    <th>Pelanggan</th>
                    <th>Game & Item</th>
                    <th>User ID Game</th>
                    <th>Harga Jual</th>
                    <th>Laba</th>
                    <th>Metode</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentOrders)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;padding:30px;color:var(--text-muted);">Belum ada transaksi top up game.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentOrders as $ro): ?>
                        <tr>
                            <td><strong class="text-mono"><?= htmlspecialchars($ro['invoice_no']) ?></strong></td>
                            <td style="font-size:0.8rem;color:var(--text-muted);"><?= format_datetime($ro['created_at']) ?></td>
                            <td><?= htmlspecialchars($ro['customer_name']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($ro['game_name']) ?></strong><br>
                                <span style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($ro['item_name']) ?></span>
                            </td>
                            <td class="text-mono" style="font-size:0.8rem;">
                                <?= htmlspecialchars($ro['user_id_game']) ?> <?= $ro['server_id_game'] ? '(' . htmlspecialchars($ro['server_id_game']) . ')' : '' ?>
                            </td>
                            <td class="text-mono font-weight-bold"><?= format_rupiah($ro['sell_price']) ?></td>
                            <td class="text-mono" style="color:var(--success);font-weight:700;">+<?= format_rupiah($ro['profit']) ?></td>
                            <td><span style="font-size:0.75rem;font-weight:600;padding:2px 6px;background:var(--bg-card-subtle);border-radius:4px;"><?= htmlspecialchars($ro['payment_method']) ?></span></td>
                            <td><?= render_status_badge($ro['order_status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Chart initialization
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('financeTrendChart').getContext('2d');
    
    function getThemeColors() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            textColor: isDark ? '#94a3b8' : '#64748b',
            gridColor: isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(0, 0, 0, 0.06)',
            tooltipBg: isDark ? '#1e293b' : '#ffffff',
            tooltipText: isDark ? '#f8fafc' : '#0f172a'
        };
    }

    let colors = getThemeColors();

    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartDates) ?>,
            datasets: [
                {
                    label: 'Pemasukan Penjualan',
                    data: <?= json_encode($chartRevenue) ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.12)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2.5,
                    pointBackgroundColor: '#6366f1',
                    pointRadius: 4
                },
                {
                    label: 'Pengeluaran Kas/Biaya',
                    data: <?= json_encode($chartExpense) ?>,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.08)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointBackgroundColor: '#ef4444',
                    pointRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        color: colors.textColor,
                        font: { family: 'Inter', weight: 600, size: 12 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': Rp ' + context.parsed.y.toLocaleString('id-ID');
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: colors.gridColor },
                    ticks: { color: colors.textColor, font: { family: 'Inter', size: 11 } }
                },
                y: {
                    grid: { color: colors.gridColor },
                    ticks: {
                        color: colors.textColor,
                        font: { family: 'Inter', size: 11 },
                        callback: function(value) {
                            return 'Rp ' + (value / 1000).toLocaleString('id-ID') + 'k';
                        }
                    }
                }
            }
        }
    });

    // Handle dynamic chart color adaptation when theme changes
    window.addEventListener('themeChanged', function() {
        const newColors = getThemeColors();
        chart.options.plugins.legend.labels.color = newColors.textColor;
        chart.options.scales.x.grid.color = newColors.gridColor;
        chart.options.scales.x.ticks.color = newColors.textColor;
        chart.options.scales.y.grid.color = newColors.gridColor;
        chart.options.scales.y.ticks.color = newColors.textColor;
        chart.update();
    });
});
</script>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
