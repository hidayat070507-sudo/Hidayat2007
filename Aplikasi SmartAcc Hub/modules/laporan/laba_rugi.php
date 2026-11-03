<?php
/**
 * SmartAcc Hub - Laporan Laba Rugi (Income Statement)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Helper to get sum of journal mutation for an account between dates
function get_account_period_balance($db, $accId, $start, $end) {
    $stmtAcc = $db->prepare("SELECT normal_balance FROM accounts WHERE id = ?");
    $stmtAcc->execute([$accId]);
    $acc = $stmtAcc->fetch();
    if (!$acc) return 0.0;

    $stmt = $db->prepare("SELECT SUM(ji.debit) as deb, SUM(ji.credit) as crd 
                          FROM journal_items ji 
                          JOIN journal_entries je ON ji.journal_id = je.id 
                          WHERE ji.account_id = ? AND je.entry_date BETWEEN ? AND ?");
    $stmt->execute([$accId, $start, $end]);
    $res = $stmt->fetch();
    $deb = (float)($res['deb'] ?? 0);
    $crd = (float)($res['crd'] ?? 0);

    return ($acc['normal_balance'] === 'KREDIT') ? ($crd - $deb) : ($deb - $crd);
}

// 1. PENDAPATAN
$incomeAccounts = $db->query("SELECT * FROM accounts WHERE type = 'PENDAPATAN' ORDER BY code ASC")->fetchAll();
$incomeList = [];
$totalPendapatan = 0;
foreach ($incomeAccounts as $acc) {
    $val = get_account_period_balance($db, (int)$acc['id'], $startDate, $endDate);
    $incomeList[] = ['name' => $acc['name'], 'code' => $acc['code'], 'amount' => $val];
    $totalPendapatan += $val;
}

// 2. BEBAN POKOK PENJUALAN (HPP)
$hppAccounts = $db->query("SELECT * FROM accounts WHERE type = 'BEBAN_HPP' ORDER BY code ASC")->fetchAll();
$hppList = [];
$totalHpp = 0;
foreach ($hppAccounts as $acc) {
    $val = get_account_period_balance($db, (int)$acc['id'], $startDate, $endDate);
    $hppList[] = ['name' => $acc['name'], 'code' => $acc['code'], 'amount' => $val];
    $totalHpp += $val;
}

// Laba Kotor
$labaKotor = $totalPendapatan - $totalHpp;

// 3. BEBAN OPERASIONAL
$expenseAccounts = $db->query("SELECT * FROM accounts WHERE type = 'BEBAN_OPERASIONAL' ORDER BY code ASC")->fetchAll();
$expenseList = [];
$totalBebanOperasional = 0;
foreach ($expenseAccounts as $acc) {
    $val = get_account_period_balance($db, (int)$acc['id'], $startDate, $endDate);
    $expenseList[] = ['name' => $acc['name'], 'code' => $acc['code'], 'amount' => $val];
    $totalBebanOperasional += $val;
}

// Laba Bersih
$labaBersih = $labaKotor - $totalBebanOperasional;

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="card-box" style="max-width:900px;margin:0 auto 24px;">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-file-invoice-dollar" style="color:var(--primary);"></i>
            Laporan Laba Rugi (Income Statement)
        </div>
        <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i> Cetak Laporan
            </button>
            <a href="<?= BASE_URL ?>/index.php?page=export&type=laba_rugi&start=<?= $startDate ?>&end=<?= $endDate ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-file-excel"></i> Ekspor CSV
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="" style="display:flex;align-items:center;gap:12px;margin-bottom:24px;background:var(--bg-card-subtle);padding:14px;border-radius:var(--radius-md);">
        <input type="hidden" name="page" value="laba_rugi">
        <span style="font-size:0.85rem;font-weight:600;color:var(--text-muted);">Periode Laporan:</span>
        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="form-control" style="width:auto;">
        <span style="color:var(--text-muted);">&mdash;</span>
        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="form-control" style="width:auto;">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Tampilkan</button>
    </form>

    <!-- Report Header Info -->
    <div style="text-align:center;margin-bottom:24px;border-bottom:2px solid var(--border-color);padding-bottom:14px;">
        <h2 style="font-size:1.35rem;font-weight:800;letter-spacing:-0.4px;margin-bottom:2px;"><?= htmlspecialchars(get_setting('company_name', 'SmartAcc Game Store')) ?></h2>
        <div style="font-size:1.05rem;font-weight:700;color:var(--primary);">LAPORAN LABA RUGI</div>
        <div style="font-size:0.8rem;color:var(--text-muted);">Periode: <?= format_date($startDate) ?> s/d <?= format_date($endDate) ?></div>
    </div>

    <!-- Statement Table -->
    <table style="width:100%;border-collapse:collapse;font-size:0.92rem;">
        <!-- PENDAPATAN SECTION -->
        <thead>
            <tr style="background:var(--bg-card-subtle);border-top:1px solid var(--border-color);border-bottom:1px solid var(--border-color);">
                <th colspan="2" style="padding:10px 14px;text-align:left;font-weight:700;color:var(--primary);">1. PENDAPATAN USAHA</th>
                <th style="padding:10px 14px;text-align:right;">JUMLAH (RP)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($incomeList as $inc): ?>
                <tr>
                    <td style="padding:8px 14px;width:100px;" class="text-mono"><?= htmlspecialchars($inc['code']) ?></td>
                    <td style="padding:8px 14px;"><?= htmlspecialchars($inc['name']) ?></td>
                    <td style="padding:8px 14px;text-align:right;" class="text-mono"><?= format_rupiah($inc['amount']) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr style="font-weight:700;border-top:1px dashed var(--border-color);background:rgba(99,102,241,0.04);">
                <td colspan="2" style="padding:10px 14px;">TOTAL PENDAPATAN USAHA:</td>
                <td style="padding:10px 14px;text-align:right;color:var(--primary);" class="text-mono"><?= format_rupiah($totalPendapatan) ?></td>
            </tr>
        </tbody>

        <!-- HPP SECTION -->
        <thead>
            <tr style="background:var(--bg-card-subtle);border-top:2px solid var(--border-color);border-bottom:1px solid var(--border-color);">
                <th colspan="2" style="padding:10px 14px;text-align:left;font-weight:700;color:var(--danger);">2. BEBAN POKOK PENJUALAN (HPP)</th>
                <th style="padding:10px 14px;text-align:right;">JUMLAH (RP)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($hppList as $hpp): ?>
                <tr>
                    <td style="padding:8px 14px;" class="text-mono"><?= htmlspecialchars($hpp['code']) ?></td>
                    <td style="padding:8px 14px;"><?= htmlspecialchars($hpp['name']) ?></td>
                    <td style="padding:8px 14px;text-align:right;" class="text-mono"><?= format_rupiah($hpp['amount']) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr style="font-weight:700;border-top:1px dashed var(--border-color);background:rgba(239,68,68,0.04);">
                <td colspan="2" style="padding:10px 14px;">TOTAL BEBAN POKOK (HPP):</td>
                <td style="padding:10px 14px;text-align:right;color:var(--danger);" class="text-mono"><?= format_rupiah($totalHpp) ?></td>
            </tr>
        </tbody>

        <!-- LABA KOTOR ROW -->
        <tfoot>
            <tr style="background:var(--bg-card-subtle);font-size:1.02rem;font-weight:800;border-top:2px solid var(--border-color);border-bottom:2px solid var(--border-color);">
                <td colspan="2" style="padding:12px 14px;text-transform:uppercase;">LABA KOTOR (GROSS PROFIT):</td>
                <td style="padding:12px 14px;text-align:right;color:var(--success);" class="text-mono"><?= format_rupiah($labaKotor) ?></td>
            </tr>
        </tfoot>

        <!-- BEBAN OPERASIONAL SECTION -->
        <thead>
            <tr style="background:var(--bg-card-subtle);border-top:2px solid var(--border-color);border-bottom:1px solid var(--border-color);">
                <th colspan="2" style="padding:10px 14px;text-align:left;font-weight:700;color:var(--warning);">3. BEBAN OPERASIONAL USAHA</th>
                <th style="padding:10px 14px;text-align:right;">JUMLAH (RP)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($expenseList as $exp): ?>
                <tr>
                    <td style="padding:8px 14px;" class="text-mono"><?= htmlspecialchars($exp['code']) ?></td>
                    <td style="padding:8px 14px;"><?= htmlspecialchars($exp['name']) ?></td>
                    <td style="padding:8px 14px;text-align:right;" class="text-mono"><?= format_rupiah($exp['amount']) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr style="font-weight:700;border-top:1px dashed var(--border-color);background:rgba(245,158,11,0.04);">
                <td colspan="2" style="padding:10px 14px;">TOTAL BEBAN OPERASIONAL:</td>
                <td style="padding:10px 14px;text-align:right;color:var(--warning);" class="text-mono"><?= format_rupiah($totalBebanOperasional) ?></td>
            </tr>
        </tbody>

        <!-- FINAL LABA BERSIH ROW -->
        <tfoot>
            <tr style="background:var(--bg-card);font-size:1.15rem;font-weight:800;border-top:3px double var(--border-color);border-bottom:3px double var(--border-color);">
                <td colspan="2" style="padding:16px 14px;text-transform:uppercase;color:<?= $labaBersih >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                    LABA BERSIH PERIODE BERJALAN (NET INCOME):
                </td>
                <td style="padding:16px 14px;text-align:right;color:<?= $labaBersih >= 0 ? 'var(--success)' : 'var(--danger)' ?>;" class="text-mono">
                    <?= format_rupiah($labaBersih) ?>
                </td>
            </tr>
        </tfoot>
    </table>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
