<?php
/**
 * SmartAcc Hub - Buku Besar (General Ledger)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();

// Fetch all accounts for dropdown
$accounts = $db->query("SELECT * FROM accounts ORDER BY code ASC")->fetchAll();

$selectedAccId = (int)($_GET['account_id'] ?? ($accounts[0]['id'] ?? 1));
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Fetch selected account details
$stmtSel = $db->prepare("SELECT * FROM accounts WHERE id = ?");
$stmtSel->execute([$selectedAccId]);
$account = $stmtSel->fetch();

// 1. Calculate Beginning Balance (Saldo Awal sebelum $startDate)
$stmtBeg = $db->prepare("SELECT SUM(ji.debit) as deb, SUM(ji.credit) as crd 
                         FROM journal_items ji 
                         JOIN journal_entries je ON ji.journal_id = je.id 
                         WHERE ji.account_id = ? AND je.entry_date < ?");
$stmtBeg->execute([$selectedAccId, $startDate]);
$beg = $stmtBeg->fetch();
$begDeb = (float)($beg['deb'] ?? 0);
$begCrd = (float)($beg['crd'] ?? 0);

if ($account['normal_balance'] === 'DEBIT') {
    $beginningBalance = $begDeb - $begCrd;
} else {
    $beginningBalance = $begCrd - $begDeb;
}

// 2. Fetch mutations during the selected period
$stmtMut = $db->prepare("SELECT ji.*, je.journal_no, je.entry_date, je.description as journal_desc, je.reference_type 
                         FROM journal_items ji 
                         JOIN journal_entries je ON ji.journal_id = je.id 
                         WHERE ji.account_id = ? AND je.entry_date BETWEEN ? AND ? 
                         ORDER BY je.entry_date ASC, je.id ASC, ji.id ASC");
$stmtMut->execute([$selectedAccId, $startDate, $endDate]);
$mutations = $stmtMut->fetchAll();

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="card-box">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-balance-scale" style="color:var(--primary);"></i>
            Buku Besar Akun (General Ledger)
        </div>
        <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">
            <i class="fas fa-print"></i> Cetak Buku Besar
        </button>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="" style="display:grid;grid-template-columns: 2fr 1fr 1fr auto;gap:14px;margin-bottom:24px;background:var(--bg-card-subtle);padding:16px;border-radius:var(--radius-md);">
        <input type="hidden" name="page" value="buku_besar">
        
        <div>
            <label style="font-size:0.75rem;font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px;">Pilih Akun Rekening:</label>
            <select name="account_id" class="form-select" onchange="this.form.submit()">
                <?php foreach ($accounts as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $a['id'] == $selectedAccId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['code']) ?> - <?= htmlspecialchars($a['name']) ?> (<?= htmlspecialchars($a['type']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="font-size:0.75rem;font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px;">Dari Tanggal:</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="form-control">
        </div>

        <div>
            <label style="font-size:0.75rem;font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px;">Sampai Tanggal:</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="form-control">
        </div>

        <div style="display:flex;align-items:flex-end;">
            <button type="submit" class="btn btn-primary" style="height:42px;"><i class="fas fa-filter"></i> Tampilkan</button>
        </div>
    </form>

    <!-- Account Details Card -->
    <div style="display:flex;justify-content:space-between;align-items:center;background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:16px 20px;margin-bottom:20px;">
        <div>
            <span class="status-badge badge-primary" style="font-size:0.7rem;"><?= htmlspecialchars($account['type']) ?></span>
            <h3 style="font-size:1.25rem;margin:6px 0 2px;font-weight:800;letter-spacing:-0.3px;">
                <?= htmlspecialchars($account['code']) ?> - <?= htmlspecialchars($account['name']) ?>
            </h3>
            <div style="font-size:0.8rem;color:var(--text-muted);">
                Saldo Normal: <strong><?= htmlspecialchars($account['normal_balance']) ?></strong>
                <?= !empty($account['description']) ? ' &bull; ' . htmlspecialchars($account['description']) : '' ?>
            </div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;font-weight:600;">Saldo Awal Periode:</div>
            <div class="text-mono" style="font-size:1.2rem;font-weight:800;color:var(--text-primary);">
                <?= format_rupiah($beginningBalance) ?>
            </div>
        </div>
    </div>

    <!-- Ledger Mutation Table -->
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th style="width:110px;">Tanggal</th>
                    <th style="width:130px;">No Jurnal</th>
                    <th>Keterangan / Transaksi</th>
                    <th style="text-align:right;width:130px;">Debit</th>
                    <th style="text-align:right;width:130px;">Kredit</th>
                    <th style="text-align:right;width:150px;">Saldo Akhir</th>
                </tr>
            </thead>
            <tbody>
                <!-- Beginning Balance Row -->
                <tr style="background:var(--bg-card-subtle);font-weight:600;">
                    <td><?= format_date($startDate) ?></td>
                    <td class="text-mono">-</td>
                    <td><em>Saldo Awal Periode</em></td>
                    <td style="text-align:right;">-</td>
                    <td style="text-align:right;">-</td>
                    <td style="text-align:right;" class="text-mono font-weight-bold"><?= format_rupiah($beginningBalance) ?></td>
                </tr>

                <?php 
                $runningBalance = $beginningBalance;
                $periodDebit = 0;
                $periodCredit = 0;
                ?>

                <?php if (empty($mutations)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted);">Tidak ada mutasi transaksi pada rentang periode ini.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($mutations as $m): ?>
                        <?php
                        $deb = (float)$m['debit'];
                        $crd = (float)$m['credit'];
                        $periodDebit += $deb;
                        $periodCredit += $crd;

                        if ($account['normal_balance'] === 'DEBIT') {
                            $runningBalance += ($deb - $crd);
                        } else {
                            $runningBalance += ($crd - $deb);
                        }
                        ?>
                        <tr>
                            <td><?= format_date($m['entry_date']) ?></td>
                            <td><strong class="text-mono" style="color:var(--primary);"><?= htmlspecialchars($m['journal_no']) ?></strong></td>
                            <td>
                                <?= htmlspecialchars($m['memo'] ?: $m['journal_desc']) ?>
                            </td>
                            <td style="text-align:right;" class="text-mono">
                                <?= $deb > 0 ? format_rupiah($deb) : '-' ?>
                            </td>
                            <td style="text-align:right;" class="text-mono">
                                <?= $crd > 0 ? format_rupiah($crd) : '-' ?>
                            </td>
                            <td style="text-align:right;font-weight:700;" class="text-mono">
                                <?= format_rupiah($runningBalance) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:var(--bg-card-subtle);font-weight:800;font-size:0.95rem;">
                    <td colspan="3" style="text-align:right;">TOTAL MUTASI PERIODE INI:</td>
                    <td style="text-align:right;" class="text-mono"><?= format_rupiah($periodDebit) ?></td>
                    <td style="text-align:right;" class="text-mono"><?= format_rupiah($periodCredit) ?></td>
                    <td style="text-align:right;color:var(--primary);" class="text-mono font-weight-bold"><?= format_rupiah($runningBalance) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
