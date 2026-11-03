<?php
/**
 * SmartAcc Hub - Laporan Keuntungan per Transaksi
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$gameFilter = $_GET['game_id'] ?? '';

$sql = "SELECT * FROM topup_orders WHERE DATE(created_at) BETWEEN ? AND ? AND order_status = 'BERHASIL'";
$params = [$startDate, $endDate];

if (!empty($gameFilter)) {
    $sql .= " AND category_id = ?";
    $params[] = $gameFilter;
}

$sql .= " ORDER BY created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Summaries
$totalOmzet = 0;
$totalModal = 0;
$totalProfit = 0;
$count = count($orders);

foreach ($orders as $o) {
    $totalOmzet += (float)$o['sell_price'];
    $totalModal += (float)$o['modal_price'];
    $totalProfit += (float)$o['profit'];
}

$avgProfit = $count > 0 ? ($totalProfit / $count) : 0;
$avgMarginPct = $totalOmzet > 0 ? round(($totalProfit / $totalOmzet) * 100, 1) : 0;

$categories = $db->query("SELECT id, name FROM game_categories ORDER BY name ASC")->fetchAll();

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<!-- Summary Stat Cards -->
<div class="stat-grid">
    <div class="stat-card indigo">
        <div class="stat-info">
            <div class="stat-label">Total Omzet Penjualan</div>
            <div class="stat-value"><?= format_rupiah($totalOmzet) ?></div>
            <div class="stat-desc"><?= $count ?> transaksi berhasil</div>
        </div>
        <div class="stat-icon indigo"><i class="fas fa-shopping-bag"></i></div>
    </div>

    <div class="stat-card rose">
        <div class="stat-info">
            <div class="stat-label">Total Modal Supplier (HPP)</div>
            <div class="stat-value"><?= format_rupiah($totalModal) ?></div>
            <div class="stat-desc">Biaya pembelian item game</div>
        </div>
        <div class="stat-icon rose"><i class="fas fa-cubes"></i></div>
    </div>

    <div class="stat-card emerald">
        <div class="stat-info">
            <div class="stat-label">Total Keuntungan Bersih</div>
            <div class="stat-value" style="color:var(--success);"><?= format_rupiah($totalProfit) ?></div>
            <div class="stat-desc">Rata-rata Margin: <strong><?= $avgMarginPct ?>%</strong></div>
        </div>
        <div class="stat-icon emerald"><i class="fas fa-hand-holding-usd"></i></div>
    </div>

    <div class="stat-card cyan">
        <div class="stat-info">
            <div class="stat-label">Rata-rata Laba / Transaksi</div>
            <div class="stat-value"><?= format_rupiah($avgProfit) ?></div>
            <div class="stat-desc">Keuntungan per order</div>
        </div>
        <div class="stat-icon cyan"><i class="fas fa-chart-pie"></i></div>
    </div>
</div>

<div class="card-box">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-receipt" style="color:var(--primary);"></i>
            Laporan Rincian Margin & Laba per Transaksi
        </div>
        <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i> Cetak Laporan
            </button>
            <a href="<?= BASE_URL ?>/index.php?page=export&type=transaksi&start=<?= $startDate ?>&end=<?= $endDate ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-file-excel"></i> Ekspor CSV
            </a>
        </div>
    </div>

    <!-- Filter Form -->
    <form method="GET" action="" style="display:flex;align-items:center;gap:12px;margin-bottom:24px;background:var(--bg-card-subtle);padding:14px;border-radius:var(--radius-md);flex-wrap:wrap;">
        <input type="hidden" name="page" value="laporan_transaksi">
        <div style="display:flex;align-items:center;gap:6px;">
            <span style="font-size:0.85rem;font-weight:600;color:var(--text-muted);">Periode:</span>
            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="form-control" style="width:auto;">
            <span style="color:var(--text-muted);">&mdash;</span>
            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="form-control" style="width:auto;">
        </div>

        <select name="game_id" class="form-select" style="width:auto;">
            <option value="">-- Semua Judul Game --</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $gameFilter == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Tampilkan</button>
    </form>

    <!-- Table -->
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Invoice & Waktu</th>
                    <th>Pelanggan</th>
                    <th>Game & Item</th>
                    <th>User ID Target</th>
                    <th style="text-align:right;">Harga Beli (Modal)</th>
                    <th style="text-align:right;">Harga Jual</th>
                    <th style="text-align:right;">Untung (Profit)</th>
                    <th style="text-align:center;">Margin (%)</th>
                    <th>Metode</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;padding:35px;color:var(--text-muted);">Tidak ada transaksi yang selesai pada filter ini.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                        <?php
                        $mPrice = (float)$o['modal_price'];
                        $sPrice = (float)$o['sell_price'];
                        $profit = (float)$o['profit'];
                        $pct = ($sPrice > 0) ? round(($profit / $sPrice) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td>
                                <strong class="text-mono"><?= htmlspecialchars($o['invoice_no']) ?></strong><br>
                                <span style="font-size:0.75rem;color:var(--text-muted);"><?= format_datetime($o['created_at']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($o['customer_name']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($o['game_name']) ?></strong><br>
                                <small style="color:var(--text-muted);"><?= htmlspecialchars($o['item_name']) ?></small>
                            </td>
                            <td class="text-mono" style="font-size:0.8rem;">
                                <?= htmlspecialchars($o['user_id_game']) ?> <?= $o['server_id_game'] ? '(' . htmlspecialchars($o['server_id_game']) . ')' : '' ?>
                            </td>
                            <td style="text-align:right;" class="text-mono color-muted"><?= format_rupiah($mPrice) ?></td>
                            <td style="text-align:right;" class="text-mono font-weight-bold"><?= format_rupiah($sPrice) ?></td>
                            <td style="text-align:right;color:var(--success);font-weight:800;" class="text-mono">
                                +<?= format_rupiah($profit) ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="status-badge <?= $pct >= 10 ? 'badge-success' : 'badge-warning' ?>" style="font-size:0.72rem;">
                                    <?= $pct ?>%
                                </span>
                            </td>
                            <td>
                                <span style="font-size:0.75rem;font-weight:600;padding:2px 6px;background:var(--bg-card-subtle);border-radius:4px;">
                                    <?= htmlspecialchars($o['payment_method']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:var(--bg-card-subtle);font-weight:800;font-size:1rem;">
                    <td colspan="4" style="text-align:right;padding:14px;">TOTAL:</td>
                    <td style="text-align:right;" class="text-mono"><?= format_rupiah($totalModal) ?></td>
                    <td style="text-align:right;color:var(--primary);" class="text-mono"><?= format_rupiah($totalOmzet) ?></td>
                    <td style="text-align:right;color:var(--success);" class="text-mono">+<?= format_rupiah($totalProfit) ?></td>
                    <td style="text-align:center;"><?= $avgMarginPct ?>%</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
