<?php
/**
 * SmartAcc Hub - Kas Masuk & Kas Keluar (Cash Transactions)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();
$currentUser = current_user();

// Handle New Cash Transaction (Kas Masuk / Kas Keluar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_cash_trans'])) {
    $transType = $_POST['trans_type']; // KAS_MASUK or KAS_KELUAR
    $category = $_POST['category'];
    $accountId = (int)$_POST['account_id']; // Kas/Bank
    $contraAccountId = (int)$_POST['contra_account_id']; // Beban / Pendapatan / Modal
    $amount = (float)$_POST['amount'];
    $transDate = $_POST['trans_date'] ?: date('Y-m-d');
    $description = trim($_POST['description']);

    if ($amount > 0 && $accountId > 0 && $contraAccountId > 0) {
        $prefix = ($transType === 'KAS_MASUK') ? 'BKM' : 'BKK';
        $transNo = generate_trans_no($prefix);

        $stmt = $db->prepare("INSERT INTO cash_transactions 
            (trans_no, trans_type, category, account_id, contra_account_id, amount, trans_date, description, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$transNo, $transType, $category, $accountId, $contraAccountId, $amount, $transDate, $description, $currentUser['username']]);

        // Auto Double-Entry Journal Entry
        $jno = generate_journal_no();
        if ($transType === 'KAS_MASUK') {
            // Debit Kas/Bank, Kredit Akun Lawan (Pendapatan/Modal)
            $items = [
                ['account_id' => $accountId, 'debit' => $amount, 'credit' => 0, 'memo' => $description],
                ['account_id' => $contraAccountId, 'debit' => 0, 'credit' => $amount, 'memo' => $description]
            ];
            create_journal_entry($db, $jno, $transDate, 'KAS_MASUK', $transNo, "Penerimaan Kas - {$description} ({$transNo})", $items, $currentUser['username']);
        } else {
            // Debit Akun Beban/Biaya, Kredit Kas/Bank
            $items = [
                ['account_id' => $contraAccountId, 'debit' => $amount, 'credit' => 0, 'memo' => $description],
                ['account_id' => $accountId, 'debit' => 0, 'credit' => $amount, 'memo' => $description]
            ];
            create_journal_entry($db, $jno, $transDate, 'KAS_KELUAR', $transNo, "Pengeluaran Kas - {$description} ({$transNo})", $items, $currentUser['username']);
        }

        log_activity('CASH_TRANSACTION', "Mencatat transaksi {$transType} {$transNo} sebesar " . format_rupiah($amount));
        set_flash('success', "Transaksi {$transNo} berhasil dicatat dan diposting ke jurnal akuntansi.");
        header('Location: ' . BASE_URL . '/index.php?page=keuangan');
        exit;
    }
}

// Filter query
$typeFilter = $_GET['type'] ?? '';
$sql = "SELECT ct.*, a.name as account_name, ca.name as contra_name 
        FROM cash_transactions ct 
        JOIN accounts a ON ct.account_id = a.id 
        JOIN accounts ca ON ct.contra_account_id = ca.id 
        WHERE 1=1";
$params = [];

if (!empty($typeFilter)) {
    $sql .= " AND ct.trans_type = ?";
    $params[] = $typeFilter;
}
$sql .= " ORDER BY ct.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Total Summary
$totalMasuk = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM cash_transactions WHERE trans_type = 'KAS_MASUK'")->fetchColumn();
$totalKeluar = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM cash_transactions WHERE trans_type = 'KAS_KELUAR'")->fetchColumn();
$netCashflow = $totalMasuk - $totalKeluar;

// Accounts for Dropdown
$cashAccounts = $db->query("SELECT id, code, name FROM accounts WHERE is_cash_bank = 1 ORDER BY code ASC")->fetchAll();
$incomeAccounts = $db->query("SELECT id, code, name FROM accounts WHERE type IN ('PENDAPATAN', 'EKUITAS') ORDER BY code ASC")->fetchAll();
$expenseAccounts = $db->query("SELECT id, code, name FROM accounts WHERE type IN ('BEBAN_OPERASIONAL', 'BEBAN_HPP') ORDER BY code ASC")->fetchAll();

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<!-- Summary Cards -->
<div class="stat-grid">
    <div class="stat-card emerald">
        <div class="stat-info">
            <div class="stat-label">Total Kas Masuk (Non-Topup)</div>
            <div class="stat-value"><?= format_rupiah($totalMasuk) ?></div>
            <div class="stat-desc">Pemasukan lain-lain & modal</div>
        </div>
        <div class="stat-icon emerald"><i class="fas fa-arrow-down"></i></div>
    </div>

    <div class="stat-card rose">
        <div class="stat-info">
            <div class="stat-label">Total Kas Keluar (Biaya & Beban)</div>
            <div class="stat-value"><?= format_rupiah($totalKeluar) ?></div>
            <div class="stat-desc">Operasional, listrik, gaji, admin</div>
        </div>
        <div class="stat-icon rose"><i class="fas fa-arrow-up"></i></div>
    </div>

    <div class="stat-card <?= $netCashflow >= 0 ? 'cyan' : 'amber' ?>">
        <div class="stat-info">
            <div class="stat-label">Arus Kas Bersih (Net Cashflow)</div>
            <div class="stat-value"><?= format_rupiah($netCashflow) ?></div>
            <div class="stat-desc">Selisih mutasi kas langsung</div>
        </div>
        <div class="stat-icon <?= $netCashflow >= 0 ? 'cyan' : 'amber' ?>"><i class="fas fa-exchange-alt"></i></div>
    </div>
</div>

<div class="card-box">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-wallet" style="color:var(--primary);"></i>
            Mutasi Kas Masuk & Kas Keluar
        </div>
        <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-success btn-sm" onclick="openCashModal('KAS_MASUK')">
                <i class="fas fa-plus"></i> Kas Masuk
            </button>
            <button type="button" class="btn btn-danger btn-sm" onclick="openCashModal('KAS_KELUAR')">
                <i class="fas fa-minus"></i> Kas Keluar (Biaya)
            </button>
        </div>
    </div>

    <!-- Filter Buttons -->
    <div style="display:flex;gap:8px;margin-bottom:18px;">
        <a href="<?= BASE_URL ?>/index.php?page=keuangan" class="btn btn-sm <?= empty($typeFilter) ? 'btn-primary' : 'btn-outline' ?>">Semua</a>
        <a href="<?= BASE_URL ?>/index.php?page=keuangan&type=KAS_MASUK" class="btn btn-sm <?= $typeFilter === 'KAS_MASUK' ? 'btn-primary' : 'btn-outline' ?>">Kas Masuk Saja</a>
        <a href="<?= BASE_URL ?>/index.php?page=keuangan&type=KAS_KELUAR" class="btn btn-sm <?= $typeFilter === 'KAS_KELUAR' ? 'btn-primary' : 'btn-outline' ?>">Kas Keluar Saja</a>
    </div>

    <!-- Transactions Table -->
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>No Bukti</th>
                    <th>Tanggal</th>
                    <th>Jenis</th>
                    <th>Akun Kas / Bank</th>
                    <th>Kategori / Akun Lawan</th>
                    <th>Jumlah Nominal</th>
                    <th>Keterangan</th>
                    <th>Pencatat</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:35px;color:var(--text-muted);">Belum ada catatan mutasi kas.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td><strong class="text-mono"><?= htmlspecialchars($t['trans_no']) ?></strong></td>
                            <td style="font-size:0.82rem;color:var(--text-muted);"><?= format_date($t['trans_date']) ?></td>
                            <td>
                                <?php if ($t['trans_type'] === 'KAS_MASUK'): ?>
                                    <span class="status-badge badge-success"><i class="fas fa-arrow-down"></i> Kas Masuk</span>
                                <?php else: ?>
                                    <span class="status-badge badge-danger"><i class="fas fa-arrow-up"></i> Kas Keluar</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($t['account_name']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($t['contra_name']) ?></strong><br>
                                <span style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($t['category']) ?></span>
                            </td>
                            <td class="text-mono font-weight-bold" style="color:<?= $t['trans_type'] === 'KAS_MASUK' ? 'var(--success)' : 'var(--danger)' ?>;">
                                <?= $t['trans_type'] === 'KAS_MASUK' ? '+' : '-' ?><?= format_rupiah($t['amount']) ?>
                            </td>
                            <td><?= htmlspecialchars($t['description'] ?: '-') ?></td>
                            <td style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($t['created_by']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Input Kas Masuk / Keluar -->
<div class="modal-overlay" id="cashModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title" id="cashModalTitle"><i class="fas fa-edit me-2"></i> Catat Mutasi Kas</h3>
            <button type="button" class="btn-close" onclick="closeModal('cashModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="save_cash_trans" value="1">
            <input type="hidden" name="trans_type" id="modalTransType" value="KAS_KELUAR">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="transDate">Tanggal Transaksi</label>
                    <input type="date" name="trans_date" id="transDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="accountId">Akun Kas / Bank yang Digunakan *</label>
                    <select name="account_id" id="accountId" class="form-select" required>
                        <?php foreach ($cashAccounts as $ca): ?>
                            <option value="<?= $ca['id'] ?>"><?= htmlspecialchars($ca['code']) ?> - <?= htmlspecialchars($ca['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" id="contraLabel">Akun Tujuan (Biaya / Pendapatan) *</label>
                    <select name="contra_account_id" id="contraAccountId" class="form-select" required>
                        <!-- Populated by JS based on type -->
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="categorySelect">Kategori Transaksi</label>
                    <select name="category" id="categorySelect" class="form-select">
                        <option value="BIAYA_OPERASIONAL">Biaya Operasional Toko</option>
                        <option value="BIAYA_ADMIN">Biaya Admin Bank / Transfer</option>
                        <option value="PENDAPATAN_LAIN">Pendapatan Lain-lain</option>
                        <option value="MODAL">Setoran Modal / Tambahan Dana</option>
                        <option value="LAIN_LAIN">Lain-lain</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="amount">Jumlah Nominal (Rp) *</label>
                    <input type="number" step="100" name="amount" id="amount" class="form-control" placeholder="Contoh: 150000" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Keterangan / Rincian Transaksi</label>
                    <textarea name="description" id="description" class="form-control" rows="2" placeholder="Contoh: Pembayaran listrik toko dan internet Indihome bulan ini"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('cashModal')">Batal</button>
                <button type="submit" class="btn btn-primary" id="btnCashSubmit">Simpan Transaksi</button>
            </div>
        </form>
    </div>
</div>

<script>
const incomeAccs = <?= json_encode($incomeAccounts) ?>;
const expenseAccs = <?= json_encode($expenseAccounts) ?>;

function openCashModal(type) {
    document.getElementById('modalTransType').value = type;
    const select = document.getElementById('contraAccountId');
    select.innerHTML = '';

    if (type === 'KAS_MASUK') {
        document.getElementById('cashModalTitle').innerHTML = '<i class="fas fa-arrow-down text-success me-2"></i> Catat Kas Masuk (Pemasukan)';
        document.getElementById('contraLabel').textContent = 'Pilih Akun Sumber Pemasukan / Modal *';
        document.getElementById('categorySelect').value = 'PENDAPATAN_LAIN';
        document.getElementById('btnCashSubmit').className = 'btn btn-success';

        incomeAccs.forEach(acc => {
            const opt = document.createElement('option');
            opt.value = acc.id;
            opt.textContent = acc.code + ' - ' + acc.name;
            select.appendChild(opt);
        });
    } else {
        document.getElementById('cashModalTitle').innerHTML = '<i class="fas fa-arrow-up text-danger me-2"></i> Catat Kas Keluar (Biaya & Pengeluaran)';
        document.getElementById('contraLabel').textContent = 'Pilih Akun Beban / Pengeluaran *';
        document.getElementById('categorySelect').value = 'BIAYA_OPERASIONAL';
        document.getElementById('btnCashSubmit').className = 'btn btn-danger';

        expenseAccs.forEach(acc => {
            const opt = document.createElement('option');
            opt.value = acc.id;
            opt.textContent = acc.code + ' - ' + acc.name;
            select.appendChild(opt);
        });
    }

    openModal('cashModal');
}

// Check action parameter in URL
document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('action') === 'masuk') {
        openCashModal('KAS_MASUK');
    } else if (params.get('action') === 'keluar') {
        openCashModal('KAS_KELUAR');
    }
});
</script>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
