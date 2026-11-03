<?php
/**
 * SmartAcc Hub - Laporan Posisi Keuangan (Neraca / Balance Sheet)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();

$asOfDate = $_GET['as_of_date'] ?? date('Y-m-d');

// 1. Fetch Assets (Aset)
$assetAccounts = $db->query("SELECT * FROM accounts WHERE type = 'ASET' ORDER BY code ASC")->fetchAll();
$totalAset = 0;
$assetsList = [];
foreach ($assetAccounts as $acc) {
    $bal = get_account_balance($db, (int)$acc['id'], $asOfDate);
    $assetsList[] = ['code' => $acc['code'], 'name' => $acc['name'], 'balance' => $bal];
    $totalAset += $bal;
}

// 2. Fetch Liabilities (Kewajiban)
$liabilityAccounts = $db->query("SELECT * FROM accounts WHERE type = 'KEWAJIBAN' ORDER BY code ASC")->fetchAll();
$totalKewajiban = 0;
$liabilitiesList = [];
foreach ($liabilityAccounts as $acc) {
    $bal = get_account_balance($db, (int)$acc['id'], $asOfDate);
    $liabilitiesList[] = ['code' => $acc['code'], 'name' => $acc['name'], 'balance' => $bal];
    $totalKewajiban += $bal;
}

// 3. Fetch Equity (Ekuitas)
$equityAccounts = $db->query("SELECT * FROM accounts WHERE type = 'EKUITAS' ORDER BY code ASC")->fetchAll();
$totalEkuitas = 0;
$equitiesList = [];
foreach ($equityAccounts as $acc) {
    $bal = get_account_balance($db, (int)$acc['id'], $asOfDate);
    $equitiesList[] = ['code' => $acc['code'], 'name' => $acc['name'], 'balance' => $bal];
    $totalEkuitas += $bal;
}

// 4. Calculate Net Income to date for Current Year/Period
// Total Pendapatan - (Total HPP + Total Beban Operasional)
$stmtInc = $db->prepare("SELECT SUM(ji.credit - ji.debit) as net_inc 
                         FROM journal_items ji 
                         JOIN journal_entries je ON ji.journal_id = je.id 
                         JOIN accounts a ON ji.account_id = a.id 
                         WHERE a.type = 'PENDAPATAN' AND je.entry_date <= ?");
$stmtInc->execute([$asOfDate]);
$totInc = (float)$stmtInc->fetchColumn();

$stmtExp = $db->prepare("SELECT SUM(ji.debit - ji.credit) as net_exp 
                         FROM journal_items ji 
                         JOIN journal_entries je ON ji.journal_id = je.id 
                         JOIN accounts a ON ji.account_id = a.id 
                         WHERE a.type IN ('BEBAN_HPP', 'BEBAN_OPERASIONAL') AND je.entry_date <= ?");
$stmtExp->execute([$asOfDate]);
$totExp = (float)$stmtExp->fetchColumn();

$labaBerjalan = $totInc - $totExp;
$totalEkuitasDanLaba = $totalEkuitas + $labaBerjalan;

$totalKewajibanDanEkuitas = $totalKewajiban + $totalEkuitasDanLaba;
$isBalanced = abs($totalAset - $totalKewajibanDanEkuitas) < 0.01;

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="card-box" style="max-width:1000px;margin:0 auto 24px;">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-columns" style="color:var(--primary);"></i>
            Laporan Posisi Keuangan (Neraca)
        </div>
        <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i> Cetak Neraca
            </button>
            <a href="<?= BASE_URL ?>/index.php?page=export&type=neraca&date=<?= $asOfDate ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-file-excel"></i> Ekspor CSV
            </a>
        </div>
    </div>

    <!-- Filter Form -->
    <form method="GET" action="" style="display:flex;align-items:center;gap:12px;margin-bottom:24px;background:var(--bg-card-subtle);padding:14px;border-radius:var(--radius-md);">
        <input type="hidden" name="page" value="neraca">
        <span style="font-size:0.85rem;font-weight:600;color:var(--text-muted);">Posisi Per Tanggal:</span>
        <input type="date" name="as_of_date" value="<?= htmlspecialchars($asOfDate) ?>" class="form-control" style="width:auto;">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Perbarui Neraca</button>
    </form>

    <!-- Report Header Info -->
    <div style="text-align:center;margin-bottom:24px;border-bottom:2px solid var(--border-color);padding-bottom:14px;">
        <h2 style="font-size:1.35rem;font-weight:800;letter-spacing:-0.4px;margin-bottom:2px;"><?= htmlspecialchars(get_setting('company_name', 'SmartAcc Game Store')) ?></h2>
        <div style="font-size:1.05rem;font-weight:700;color:var(--primary);">LAPORAN POSISI KEUANGAN (NERACA)</div>
        <div style="font-size:0.8rem;color:var(--text-muted);">Per Tanggal: <?= format_date($asOfDate) ?></div>
    </div>

    <!-- 2-Column Balance Sheet Layout -->
    <div style="display:grid;grid-template-columns: 1fr 1fr;gap:24px;">
        <!-- Left: ASET (Aktiva) -->
        <div style="border:1px solid var(--border-color);border-radius:var(--radius-md);overflow:hidden;">
            <div style="background:var(--primary-light);padding:12px 16px;border-bottom:1px solid var(--border-color);font-weight:800;color:var(--primary);font-size:1rem;">
                ASET (HARTA USAHA)
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
                <tbody>
                    <?php foreach ($assetsList as $ast): ?>
                        <tr style="border-bottom:1px solid var(--border-color);">
                            <td style="padding:10px 14px;" class="text-mono"><?= htmlspecialchars($ast['code']) ?></td>
                            <td style="padding:10px 14px;"><?= htmlspecialchars($ast['name']) ?></td>
                            <td style="padding:10px 14px;text-align:right;" class="text-mono font-weight-bold"><?= format_rupiah($ast['balance']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--bg-card-subtle);font-weight:800;font-size:1.05rem;">
                        <td colspan="2" style="padding:14px;">TOTAL ASET:</td>
                        <td style="padding:14px;text-align:right;color:var(--primary);" class="text-mono"><?= format_rupiah($totalAset) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Right: KEWAJIBAN & EKUITAS (Pasiva) -->
        <div style="border:1px solid var(--border-color);border-radius:var(--radius-md);overflow:hidden;display:flex;flex-direction:column;justify-content:space-between;">
            <div>
                <!-- Kewajiban -->
                <div style="background:var(--warning-bg);padding:12px 16px;border-bottom:1px solid var(--warning-border);font-weight:800;color:var(--warning);font-size:1rem;">
                    KEWAJIBAN (UTANG)
                </div>
                <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
                    <tbody>
                        <?php if (empty($liabilitiesList)): ?>
                            <tr><td colspan="3" style="padding:10px 14px;color:var(--text-muted);">Tidak ada kewajiban.</td></tr>
                        <?php else: ?>
                            <?php foreach ($liabilitiesList as $liab): ?>
                                <tr style="border-bottom:1px solid var(--border-color);">
                                    <td style="padding:8px 14px;width:70px;" class="text-mono"><?= htmlspecialchars($liab['code']) ?></td>
                                    <td style="padding:8px 14px;"><?= htmlspecialchars($liab['name']) ?></td>
                                    <td style="padding:8px 14px;text-align:right;" class="text-mono"><?= format_rupiah($liab['balance']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <tr style="font-weight:700;background:rgba(245,158,11,0.05);border-bottom:2px solid var(--border-color);">
                            <td colspan="2" style="padding:8px 14px;">Total Kewajiban:</td>
                            <td style="padding:8px 14px;text-align:right;" class="text-mono"><?= format_rupiah($totalKewajiban) ?></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Ekuitas -->
                <div style="background:var(--success-bg);padding:12px 16px;border-bottom:1px solid var(--success-border);border-top:1px solid var(--border-color);font-weight:800;color:var(--success);font-size:1rem;">
                    EKUITAS (MODAL)
                </div>
                <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
                    <tbody>
                        <?php foreach ($equitiesList as $eq): ?>
                            <tr style="border-bottom:1px solid var(--border-color);">
                                <td style="padding:8px 14px;width:70px;" class="text-mono"><?= htmlspecialchars($eq['code']) ?></td>
                                <td style="padding:8px 14px;"><?= htmlspecialchars($eq['name']) ?></td>
                                <td style="padding:8px 14px;text-align:right;" class="text-mono"><?= format_rupiah($eq['balance']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="border-bottom:1px solid var(--border-color);">
                            <td style="padding:8px 14px;" class="text-mono">L/R</td>
                            <td style="padding:8px 14px;"><em>Laba Bersih Periode Berjalan</em></td>
                            <td style="padding:8px 14px;text-align:right;color:var(--success);" class="text-mono font-weight-bold"><?= format_rupiah($labaBerjalan) ?></td>
                        </tr>
                        <tr style="font-weight:700;background:rgba(16,185,129,0.05);">
                            <td colspan="2" style="padding:8px 14px;">Total Ekuitas & Laba:</td>
                            <td style="padding:8px 14px;text-align:right;" class="text-mono"><?= format_rupiah($totalEkuitasDanLaba) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Total Kewajiban & Ekuitas Footer -->
            <div style="background:var(--bg-card-subtle);font-weight:800;font-size:1.05rem;padding:14px;display:flex;justify-content:space-between;border-top:2px solid var(--border-color);">
                <span>TOTAL KEWAJIBAN & EKUITAS:</span>
                <span class="text-mono" style="color:var(--primary);"><?= format_rupiah($totalKewajibanDanEkuitas) ?></span>
            </div>
        </div>
    </div>

    <!-- Balance Status Indicator -->
    <div style="text-align:center;margin-top:24px;padding:14px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-md);">
        <?php if ($isBalanced): ?>
            <span class="status-badge badge-success" style="font-size:0.9rem;padding:8px 22px;">
                <i class="fas fa-check-circle me-1"></i> NERACA SEIMBANG: TOTAL ASET (<?= format_rupiah($totalAset) ?>) = TOTAL KEWAJIBAN & EKUITAS (<?= format_rupiah($totalKewajibanDanEkuitas) ?>)
            </span>
        <?php else: ?>
            <span class="status-badge badge-danger" style="font-size:0.9rem;padding:8px 22px;">
                <i class="fas fa-exclamation-triangle me-1"></i> NERACA TIDAK BALANCE: Selisih Rp <?= format_rupiah(abs($totalAset - $totalKewajibanDanEkuitas)) ?>
            </span>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
