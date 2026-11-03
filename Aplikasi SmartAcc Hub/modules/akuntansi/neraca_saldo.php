<?php
/**
 * SmartAcc Hub - Neraca Saldo (Trial Balance)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();

$asOfDate = $_GET['as_of_date'] ?? date('Y-m-d');

// Fetch all accounts
$accounts = $db->query("SELECT * FROM accounts ORDER BY code ASC")->fetchAll();

$trialData = [];
$totalDebit = 0;
$totalCredit = 0;

foreach ($accounts as $acc) {
    $bal = get_account_balance($db, (int)$acc['id'], $asOfDate);
    
    $debitVal = 0;
    $creditVal = 0;

    if ($acc['normal_balance'] === 'DEBIT') {
        if ($bal >= 0) {
            $debitVal = $bal;
        } else {
            $creditVal = abs($bal);
        }
    } else {
        if ($bal >= 0) {
            $creditVal = $bal;
        } else {
            $debitVal = abs($bal);
        }
    }

    $totalDebit += $debitVal;
    $totalCredit += $creditVal;

    $trialData[] = [
        'code' => $acc['code'],
        'name' => $acc['name'],
        'type' => $acc['type'],
        'normal_balance' => $acc['normal_balance'],
        'debit' => $debitVal,
        'credit' => $creditVal
    ];
}

$isBalanced = abs($totalDebit - $totalCredit) < 0.01;

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="card-box">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-table" style="color:var(--primary);"></i>
            Neraca Saldo (Trial Balance)
        </div>
        <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i> Cetak Neraca Saldo
            </button>
            <a href="<?= BASE_URL ?>/index.php?page=export&type=neraca_saldo&date=<?= $asOfDate ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-file-excel"></i> Ekspor CSV
            </a>
        </div>
    </div>

    <!-- Date Filter -->
    <form method="GET" action="" style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
        <input type="hidden" name="page" value="neraca_saldo">
        <label style="font-size:0.85rem;font-weight:600;color:var(--text-muted);">Posisi Per Tanggal:</label>
        <input type="date" name="as_of_date" value="<?= htmlspecialchars($asOfDate) ?>" class="form-control" style="width:auto;">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Perbarui</button>
    </form>

    <!-- Table -->
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th style="width:120px;">Kode Akun</th>
                    <th>Nama Akun</th>
                    <th style="width:150px;">Kelompok</th>
                    <th style="width:130px;">Saldo Normal</th>
                    <th style="text-align:right;width:180px;">Debit</th>
                    <th style="text-align:right;width:180px;">Kredit</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trialData as $row): ?>
                    <tr>
                        <td class="text-mono"><strong><?= htmlspecialchars($row['code']) ?></strong></td>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                        <td><span class="status-badge badge-secondary" style="font-size:0.68rem;"><?= htmlspecialchars($row['type']) ?></span></td>
                        <td class="text-mono" style="font-size:0.8rem;"><?= htmlspecialchars($row['normal_balance']) ?></td>
                        <td style="text-align:right;" class="text-mono">
                            <?= $row['debit'] > 0 ? format_rupiah($row['debit']) : '-' ?>
                        </td>
                        <td style="text-align:right;" class="text-mono">
                            <?= $row['credit'] > 0 ? format_rupiah($row['credit']) : '-' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:var(--bg-card-subtle);font-weight:800;font-size:1.05rem;">
                    <td colspan="4" style="text-align:right;padding:16px;">TOTAL NERACA SALDO:</td>
                    <td style="text-align:right;color:var(--primary);" class="text-mono"><?= format_rupiah($totalDebit) ?></td>
                    <td style="text-align:right;color:var(--primary);" class="text-mono"><?= format_rupiah($totalCredit) ?></td>
                </tr>
                <tr style="background:var(--bg-card);">
                    <td colspan="6" style="text-align:center;padding:12px;">
                        <?php if ($isBalanced): ?>
                            <span class="status-badge badge-success" style="font-size:0.85rem;padding:8px 18px;">
                                <i class="fas fa-check-circle me-1"></i> STATUS: SEIMBANG / BALANCE (DEBIT = KREDIT)
                            </span>
                        <?php else: ?>
                            <span class="status-badge badge-danger" style="font-size:0.85rem;padding:8px 18px;">
                                <i class="fas fa-exclamation-triangle me-1"></i> STATUS: TIDAK BALANCE (Selisih: <?= format_rupiah(abs($totalDebit - $totalCredit)) ?>)
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
