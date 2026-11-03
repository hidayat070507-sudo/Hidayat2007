<?php
/**
 * SmartAcc Hub - Laporan Penjualan per Game
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Query game performance
$sql = "SELECT 
            gc.id as category_id,
            gc.name as game_name,
            gc.publisher,
            COUNT(o.id) as total_orders,
            COALESCE(SUM(o.sell_price), 0) as total_omzet,
            COALESCE(SUM(o.modal_price), 0) as total_modal,
            COALESCE(SUM(o.profit), 0) as total_profit
        FROM game_categories gc
        LEFT JOIN topup_orders o ON gc.id = o.category_id AND DATE(o.created_at) BETWEEN ? AND ? AND o.order_status = 'BERHASIL'
        GROUP BY gc.id, gc.name, gc.publisher
        ORDER BY total_omzet DESC";

$stmt = $db->prepare($sql);
$stmt->execute([$startDate, $endDate]);
$gameStats = $stmt->fetchAll();

// Grand Totals
$grandOrders = 0;
$grandOmzet = 0;
$grandModal = 0;
$grandProfit = 0;

$chartLabels = [];
$chartOmzetData = [];
$chartProfitData = [];

foreach ($gameStats as $gs) {
    $grandOrders += (int)$gs['total_orders'];
    $grandOmzet += (float)$gs['total_omzet'];
    $grandModal += (float)$gs['total_modal'];
    $grandProfit += (float)$gs['total_profit'];

    if ((float)$gs['total_omzet'] > 0) {
        $chartLabels[] = $gs['game_name'];
        $chartOmzetData[] = (float)$gs['total_omzet'];
        $chartProfitData[] = (float)$gs['total_profit'];
    }
}

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="card-box">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-trophy" style="color:var(--primary);"></i>
            Laporan Kinerja & Penjualan per Game
        </div>
        <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i> Cetak Laporan
            </button>
            <a href="<?= BASE_URL ?>/index.php?page=export&type=per_game&start=<?= $startDate ?>&end=<?= $endDate ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-file-excel"></i> Ekspor CSV
            </a>
        </div>
    </div>

    <!-- Filter Form -->
    <form method="GET" action="" style="display:flex;align-items:center;gap:12px;margin-bottom:24px;background:var(--bg-card-subtle);padding:14px;border-radius:var(--radius-md);">
        <input type="hidden" name="page" value="laporan_game">
        <span style="font-size:0.85rem;font-weight:600;color:var(--text-muted);">Periode Transaksi:</span>
        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="form-control" style="width:auto;">
        <span style="color:var(--text-muted);">&mdash;</span>
        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="form-control" style="width:auto;">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Tampilkan</button>
    </form>

    <!-- Chart Row -->
    <?php if (!empty($chartLabels)): ?>
        <div style="display:grid;grid-template-columns: 1fr;gap:20px;margin-bottom:24px;">
            <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:20px;">
                <div style="font-size:0.95rem;font-weight:700;margin-bottom:14px;">Grafik Komparasi Omzet & Keuntungan per Game</div>
                <div style="height:280px;width:100%;">
                    <canvas id="gameBarChart"></canvas>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Nama Game</th>
                    <th>Publisher</th>
                    <th style="text-align:center;">Pesanan Berhasil</th>
                    <th style="text-align:right;">Total Omzet (Penjualan)</th>
                    <th style="text-align:right;">Total Modal (HPP)</th>
                    <th style="text-align:right;">Total Keuntungan</th>
                    <th style="text-align:center;">Margin (%)</th>
                    <th style="text-align:center;">Kontribusi Omzet</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gameStats as $row): ?>
                    <?php
                    $omzet = (float)$row['total_omzet'];
                    $modal = (float)$row['total_modal'];
                    $profit = (float)$row['total_profit'];
                    $marginPct = ($omzet > 0) ? round(($profit / $omzet) * 100, 1) : 0;
                    $sharePct = ($grandOmzet > 0) ? round(($omzet / $grandOmzet) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td>
                            <strong style="font-size:0.92rem;"><?= htmlspecialchars($row['game_name']) ?></strong>
                        </td>
                        <td style="color:var(--text-muted);"><?= htmlspecialchars($row['publisher'] ?: '-') ?></td>
                        <td style="text-align:center;">
                            <span class="status-badge badge-secondary" style="font-size:0.75rem;">
                                <?= (int)$row['total_orders'] ?> Order
                            </span>
                        </td>
                        <td style="text-align:right;" class="text-mono font-weight-bold">
                            <?= format_rupiah($omzet) ?>
                        </td>
                        <td style="text-align:right;color:var(--text-muted);" class="text-mono">
                            <?= format_rupiah($modal) ?>
                        </td>
                        <td style="text-align:right;color:var(--success);font-weight:800;" class="text-mono">
                            +<?= format_rupiah($profit) ?>
                        </td>
                        <td style="text-align:center;">
                            <span class="status-badge <?= $marginPct > 12 ? 'badge-success' : 'badge-info' ?>" style="font-size:0.75rem;">
                                <?= $marginPct ?>%
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <div style="font-size:0.75rem;font-weight:700;"><?= $sharePct ?>%</div>
                            <div style="width:70px;background:var(--border-color);height:5px;border-radius:10px;margin:2px auto 0;overflow:hidden;">
                                <div style="width:<?= $sharePct ?>%;background:var(--primary);height:100%;"></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:var(--bg-card-subtle);font-weight:800;font-size:1rem;">
                    <td colspan="2" style="text-align:right;padding:14px;">TOTAL KESELURUHAN:</td>
                    <td style="text-align:center;" class="text-mono"><?= $grandOrders ?> Transaksi</td>
                    <td style="text-align:right;color:var(--primary);" class="text-mono"><?= format_rupiah($grandOmzet) ?></td>
                    <td style="text-align:right;color:var(--danger);" class="text-mono"><?= format_rupiah($grandModal) ?></td>
                    <td style="text-align:right;color:var(--success);" class="text-mono">+<?= format_rupiah($grandProfit) ?></td>
                    <td style="text-align:center;">
                        <?= $grandOmzet > 0 ? round(($grandProfit / $grandOmzet) * 100, 1) : 0 ?>%
                    </td>
                    <td style="text-align:center;">100%</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php if (!empty($chartLabels)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('gameBarChart').getContext('2d');
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor = isDark ? '#94a3b8' : '#64748b';
    const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                {
                    label: 'Omzet Penjualan (Rp)',
                    data: <?= json_encode($chartOmzetData) ?>,
                    backgroundColor: 'rgba(99, 102, 241, 0.85)',
                    borderRadius: 6
                },
                {
                    label: 'Keuntungan Bersih (Rp)',
                    data: <?= json_encode($chartProfitData) ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.85)',
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    grid: { color: gridColor },
                    ticks: { color: textColor, font: { family: 'Inter', size: 11 } }
                },
                y: {
                    grid: { color: gridColor },
                    ticks: {
                        color: textColor,
                        font: { family: 'Inter', size: 11 },
                        callback: val => 'Rp ' + (val / 1000).toLocaleString('id-ID') + 'k'
                    }
                }
            },
            plugins: {
                legend: {
                    labels: { color: textColor, font: { family: 'Inter', weight: 600 } }
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
