<?php
/**
 * SmartAcc Hub - Laporan Arus Kas (Cash Flow Statement)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// 1. Penerimaan dari Penjualan Topup (Kas & Bank)
$stmtTopup = $db->prepare("SELECT COALESCE(SUM(sell_price), 0) FROM topup_orders 
                           WHERE DATE(created_at) BETWEEN ? AND ? AND order_status = 'BERHASIL' AND payment_method != 'PIUTANG'");
$stmtTopup->execute([$startDate, $endDate]);
$cashFromSales = (float)$stmtTopup->fetchColumn();

// 2. Penerimaan Kas Masuk Lain-lain
$stmtOthIn = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM cash_transactions 
                           WHERE trans_date BETWEEN ? AND ? AND trans_type = 'KAS_MASUK' AND category != 'MODAL'");
$stmtOthIn->execute([$startDate, $endDate]);
$cashFromOtherIn = (float)$stmtOthIn->fetchColumn();

// 3. Pengeluaran Pembayaran Beban Operasional & Biaya Kasir
$stmtExp = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM cash_transactions 
                         WHERE trans_date BETWEEN ? AND ? AND trans_type = 'KAS_KELUAR'");
$stmtExp->execute([$startDate, $endDate]);
$cashForExpenses = (float)$stmtExp->fetchColumn();

// 4. Pengeluaran Pembelian Saldo Supplier (Deposit Saldo)
$stmtDep = $db->prepare("SELECT COALESCE(SUM(total_deducted), 0) FROM supplier_deposits 
                         WHERE deposit_date BETWEEN ? AND ? AND status = 'BERHASIL'");
$stmtDep->execute([$startDate, $endDate]);
$cashForSupplierDeposits = (float)$stmtDep->fetchColumn();

// Net Cashflow from Operations
$netCashOps = ($cashFromSales + $cashFromOtherIn) - ($cashForExpenses + $cashForSupplierDeposits);

// 5. Arus Kas dari Aktivitas Pendanaan (Setoran Modal Pemilik)
$stmtModal = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM cash_transactions 
                           WHERE trans_date BETWEEN ? AND ? AND trans_type = 'KAS_MASUK' AND category = 'MODAL'");
$stmtModal->execute([$startDate, $endDate]);
$cashFromCapital = (float)$stmtModal->fetchColumn();

$netCashFunding = $cashFromCapital;

// Net Change in Cash
$netCashChange = $netCashOps + $netCashFunding;

// Saldo Kas & Bank Awal dan Akhir
$cashBankAccounts = $db->query("SELECT id FROM accounts WHERE is_cash_bank = 1")->fetchAll(PDO::FETCH_COLUMN);
$initialCash = 0;
$finalCash = 0;

$prevDay = date('Y-m-d', strtotime("$startDate -1 day"));
foreach ($cashBankAccounts as $accId) {
    $initialCash += get_account_balance($db, (int)$accId, $prevDay);
    $finalCash += get_account_balance($db, (int)$accId, $endDate);
}

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="card-box" style="max-width:900px;margin:0 auto 24px;">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-money-bill-wave" style="color:var(--primary);"></i>
            Laporan Arus Kas (Cash Flow Statement)
        </div>
        <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i> Cetak Laporan
            </button>
            <a href="<?= BASE_URL ?>/index.php?page=export&type=arus_kas&start=<?= $startDate ?>&end=<?= $endDate ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-file-excel"></i> Ekspor CSV
            </a>
        </div>
    </div>

    <!-- Filter Form -->
    <form method="GET" action="" style="display:flex;align-items:center;gap:12px;margin-bottom:24px;background:var(--bg-card-subtle);padding:14px;border-radius:var(--radius-md);">
        <input type="hidden" name="page" value="arus_kas">
        <span style="font-size:0.85rem;font-weight:600;color:var(--text-muted);">Periode Arus Kas:</span>
        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="form-control" style="width:auto;">
        <span style="color:var(--text-muted);">&mdash;</span>
        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="form-control" style="width:auto;">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Tampilkan</button>
    </form>

    <!-- Report Header Info -->
    <div style="text-align:center;margin-bottom:24px;border-bottom:2px solid var(--border-color);padding-bottom:14px;">
        <h2 style="font-size:1.35rem;font-weight:800;letter-spacing:-0.4px;margin-bottom:2px;"><?= htmlspecialchars(get_setting('company_name', 'SmartAcc Game Store')) ?></h2>
        <div style="font-size:1.05rem;font-weight:700;color:var(--primary);">LAPORAN ARUS KAS</div>
        <div style="font-size:0.8rem;color:var(--text-muted);">Periode: <?= format_date($startDate) ?> s/d <?= format_date($endDate) ?></div>
    </div>

    <!-- Cashflow Table -->
    <table style="width:100%;border-collapse:collapse;font-size:0.92rem;">
        <!-- ARUS KAS OPERASIONAL -->
        <thead>
            <tr style="background:var(--bg-card-subtle);border-top:1px solid var(--border-color);border-bottom:1px solid var(--border-color);">
                <th style="padding:10px 14px;text-align:left;font-weight:700;color:var(--primary);">ARUS KAS DARI AKTIVITAS OPERASIONAL</th>
                <th style="padding:10px 14px;text-align:right;width:200px;">JUMLAH (RP)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding:8px 14px;">Penerimaan Kas dari Penjualan Top Up Game</td>
                <td style="padding:8px 14px;text-align:right;" class="text-mono">+<?= format_rupiah($cashFromSales) ?></td>
            </tr>
            <tr>
                <td style="padding:8px 14px;">Penerimaan Kas dari Pemasukan Lain-lain</td>
                <td style="padding:8px 14px;text-align:right;" class="text-mono">+<?= format_rupiah($cashFromOtherIn) ?></td>
            </tr>
            <tr>
                <td style="padding:8px 14px;color:var(--danger);">Pengeluaran Kas untuk Biaya Operasional & Admin</td>
                <td style="padding:8px 14px;text-align:right;color:var(--danger);" class="text-mono">-<?= format_rupiah($cashForExpenses) ?></td>
            </tr>
            <tr>
                <td style="padding:8px 14px;color:var(--danger);">Pengeluaran Kas untuk Pembelian / Deposit Saldo Supplier</td>
                <td style="padding:8px 14px;text-align:right;color:var(--danger);" class="text-mono">-<?= format_rupiah($cashForSupplierDeposits) ?></td>
            </tr>
            <tr style="font-weight:700;background:rgba(99,102,241,0.04);border-top:1px dashed var(--border-color);">
                <td style="padding:10px 14px;">Arus Kas Bersih dari Aktivitas Operasional:</td>
                <td style="padding:10px 14px;text-align:right;color:<?= $netCashOps >= 0 ? 'var(--success)' : 'var(--danger)' ?>;" class="text-mono">
                    <?= format_rupiah($netCashOps) ?>
                </td>
            </tr>
        </tbody>

        <!-- ARUS KAS PENDANAAN -->
        <thead>
            <tr style="background:var(--bg-card-subtle);border-top:2px solid var(--border-color);border-bottom:1px solid var(--border-color);">
                <th style="padding:10px 14px;text-align:left;font-weight:700;color:var(--info);">ARUS KAS DARI AKTIVITAS PENDANAAN (MODAL)</th>
                <th style="padding:10px 14px;text-align:right;">JUMLAH (RP)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding:8px 14px;">Setoran Tambahan Modal Usaha Pemilik</td>
                <td style="padding:8px 14px;text-align:right;" class="text-mono">+<?= format_rupiah($cashFromCapital) ?></td>
            </tr>
            <tr style="font-weight:700;background:rgba(6,182,212,0.04);border-top:1px dashed var(--border-color);">
                <td style="padding:10px 14px;">Arus Kas Bersih dari Aktivitas Pendanaan:</td>
                <td style="padding:10px 14px;text-align:right;color:var(--info);" class="text-mono">
                    <?= format_rupiah($netCashFunding) ?>
                </td>
            </tr>
        </tbody>

        <!-- REKAPITULASI SALDO KAS -->
        <tfoot>
            <tr style="background:var(--bg-card-subtle);font-size:1rem;font-weight:800;border-top:2px solid var(--border-color);">
                <td style="padding:12px 14px;">KENAIKAN / (PENURUNAN) KAS BERSIH PERIODE INI:</td>
                <td style="padding:12px 14px;text-align:right;color:<?= $netCashChange >= 0 ? 'var(--success)' : 'var(--danger)' ?>;" class="text-mono">
                    <?= format_rupiah($netCashChange) ?>
                </td>
            </tr>
            <tr style="border-top:1px solid var(--border-color);">
                <td style="padding:10px 14px;color:var(--text-muted);">Saldo Kas & Bank pada Awal Periode:</td>
                <td style="padding:10px 14px;text-align:right;" class="text-mono"><?= format_rupiah($initialCash) ?></td>
            </tr>
            <tr style="background:var(--bg-card);font-size:1.15rem;font-weight:800;border-top:3px double var(--border-color);border-bottom:3px double var(--border-color);">
                <td style="padding:16px 14px;color:var(--primary);">
                    SALDO KAS & BANK PADA AKHIR PERIODE:
                </td>
                <td style="padding:16px 14px;text-align:right;color:var(--primary);" class="text-mono">
                    <?= format_rupiah($finalCash) ?>
                </td>
            </tr>
        </tfoot>
    </table>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
