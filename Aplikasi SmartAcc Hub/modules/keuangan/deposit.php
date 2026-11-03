<?php
/**
 * SmartAcc Hub - Deposit Saldo Supplier
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();
$currentUser = current_user();

// Handle New Deposit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_deposit'])) {
    $supplierId = (int)$_POST['supplier_id'];
    $accountId = (int)$_POST['account_id']; // Sumber pembayaran (Kas / Bank)
    $amount = (float)$_POST['amount'];
    $adminFee = (float)($_POST['admin_fee'] ?? 0);
    $totalDeducted = $amount + $adminFee;
    $depositDate = $_POST['deposit_date'] ?: date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');

    if ($supplierId > 0 && $accountId > 0 && $amount > 0) {
        $prefix = 'DEP-' . date('Ymd') . '-';
        $stmtLast = $db->prepare("SELECT deposit_no FROM supplier_deposits WHERE deposit_no LIKE ? ORDER BY id DESC LIMIT 1");
        $stmtLast->execute([$prefix . '%']);
        $last = $stmtLast->fetchColumn();
        $depNo = $prefix . str_pad($last ? ((int)substr($last, strlen($prefix)) + 1) : 1, 3, '0', STR_PAD_LEFT);

        // 1. Insert deposit record
        $stmt = $db->prepare("INSERT INTO supplier_deposits 
            (deposit_no, supplier_id, account_id, amount, admin_fee, total_deducted, deposit_date, status, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'BERHASIL', ?, ?)");
        $stmt->execute([$depNo, $supplierId, $accountId, $amount, $adminFee, $totalDeducted, $depositDate, $notes, $currentUser['username']]);

        // 2. Increase supplier balance
        $db->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ?")->execute([$amount, $supplierId]);

        // 3. Accounting Double-Entry Journal
        // Debit: Saldo Supplier Top Up (1104) = $amount
        // Debit: Beban Admin Bank (6104) = $adminFee (if > 0)
        // Kredit: Kas / Bank (accountId) = $totalDeducted
        $accSup = $db->query("SELECT id FROM accounts WHERE code='1104'")->fetchColumn();
        $accFee = $db->query("SELECT id FROM accounts WHERE code='6104'")->fetchColumn();

        $jno = generate_journal_no();
        $items = [
            ['account_id' => $accSup, 'debit' => $amount, 'credit' => 0, 'memo' => 'Top up deposit supplier ' . $depNo],
            ['account_id' => $accountId, 'debit' => 0, 'credit' => $totalDeducted, 'memo' => 'Pengeluaran pembayaran deposit ' . $depNo]
        ];
        if ($adminFee > 0) {
            $items[] = ['account_id' => $accFee, 'debit' => $adminFee, 'credit' => 0, 'memo' => 'Biaya transfer bank ' . $depNo];
        }

        create_journal_entry($db, $jno, $depositDate, 'DEPOSIT', $depNo, "Deposit Saldo Supplier ({$depNo})", $items, $currentUser['username']);

        // Fetch supplier name for log
        $supName = $db->query("SELECT name FROM suppliers WHERE id = $supplierId")->fetchColumn();
        log_activity('SUPPLIER_DEPOSIT', "Isi deposit {$supName} sebesar " . format_rupiah($amount) . " (No: {$depNo})");
        set_flash('success', "Deposit sebesar " . format_rupiah($amount) . " untuk {$supName} berhasil disimpan!");
        header('Location: ' . BASE_URL . '/index.php?page=deposit');
        exit;
    }
}

// Fetch suppliers, bank accounts, and deposit history
$suppliers = $db->query("SELECT * FROM suppliers ORDER BY id ASC")->fetchAll();
$cashAccounts = $db->query("SELECT id, code, name FROM accounts WHERE is_cash_bank = 1 ORDER BY code ASC")->fetchAll();

$deposits = $db->query("SELECT d.*, s.name as supplier_name, a.name as account_name 
                        FROM supplier_deposits d 
                        JOIN suppliers s ON d.supplier_id = s.id 
                        JOIN accounts a ON d.account_id = a.id 
                        ORDER BY d.id DESC")->fetchAll();

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<!-- Supplier Balance Cards Overview -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:20px;margin-bottom:24px;">
    <?php foreach ($suppliers as $s): ?>
        <div class="card-box" style="margin-bottom:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <span class="status-badge <?= $s['balance'] < 1000000 ? 'badge-warning' : 'badge-success' ?>">
                    <i class="fas fa-server"></i> <?= $s['balance'] < 1000000 ? 'Saldo Menipis' : 'Saldo Aman' ?>
                </span>
                <span style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($s['phone'] ?: 'No Kontak') ?></span>
            </div>
            <div style="font-size:1.1rem;font-weight:700;"><?= htmlspecialchars($s['name']) ?></div>
            <div style="font-size:1.55rem;font-weight:800;color:var(--primary);font-family:'JetBrains Mono', monospace;margin:8px 0;">
                <?= format_rupiah($s['balance']) ?>
            </div>
            <button type="button" class="btn btn-primary btn-sm" style="width:100%;margin-top:6px;" onclick="openDepositForSupplier(<?= $s['id'] ?>)">
                <i class="fas fa-plus-circle"></i> Tambah Deposit Saldo
            </button>
        </div>
    <?php endforeach; ?>
</div>

<div class="card-box">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-history" style="color:var(--primary);"></i>
            Riwayat Deposit Saldo Supplier
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('depositModal')">
            <i class="fas fa-plus"></i> Form Deposit Baru
        </button>
    </div>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>No Deposit</th>
                    <th>Tanggal</th>
                    <th>Nama Supplier</th>
                    <th>Sumber Pembayaran</th>
                    <th>Nominal Masuk</th>
                    <th>Biaya Admin</th>
                    <th>Total Keluar</th>
                    <th>Status</th>
                    <th>Pencatat</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($deposits)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;padding:35px;color:var(--text-muted);">Belum ada riwayat transaksi deposit supplier.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($deposits as $d): ?>
                        <tr>
                            <td><strong class="text-mono"><?= htmlspecialchars($d['deposit_no']) ?></strong></td>
                            <td style="font-size:0.82rem;color:var(--text-muted);"><?= format_date($d['deposit_date']) ?></td>
                            <td><strong><?= htmlspecialchars($d['supplier_name']) ?></strong></td>
                            <td><?= htmlspecialchars($d['account_name']) ?></td>
                            <td class="text-mono font-weight-bold" style="color:var(--success);">+<?= format_rupiah($d['amount']) ?></td>
                            <td class="text-mono" style="color:var(--text-muted);"><?= format_rupiah($d['admin_fee']) ?></td>
                            <td class="text-mono font-weight-bold"><?= format_rupiah($d['total_deducted']) ?></td>
                            <td><?= render_status_badge($d['status']) ?></td>
                            <td style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($d['created_by']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Form Deposit Baru -->
<div class="modal-overlay" id="depositModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-university me-2"></i> Tambah Deposit Saldo Supplier</h3>
            <button type="button" class="btn-close" onclick="closeModal('depositModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="save_deposit" value="1">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="depositSupplierId">Pilih Supplier Target *</label>
                    <select name="supplier_id" id="depositSupplierId" class="form-select" required>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (Saldo saat ini: <?= format_rupiah($s['balance']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="depositAccountId">Sumber Dana Pembayaran *</label>
                    <select name="account_id" id="depositAccountId" class="form-select" required>
                        <?php foreach ($cashAccounts as $ca): ?>
                            <option value="<?= $ca['id'] ?>"><?= htmlspecialchars($ca['code']) ?> - <?= htmlspecialchars($ca['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:grid;grid-template-columns: 1fr 1fr;gap:14px;">
                    <div class="form-group">
                        <label class="form-label" for="depAmount">Nominal Deposit Masuk (Rp) *</label>
                        <input type="number" step="1000" name="amount" id="depAmount" class="form-control" placeholder="Contoh: 1000000" required oninput="calcTotalDeduct()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="depFee">Biaya Admin Bank (Rp)</label>
                        <input type="number" step="100" name="admin_fee" id="depFee" class="form-control" placeholder="0" value="0" oninput="calcTotalDeduct()">
                    </div>
                </div>

                <div style="background:var(--bg-card-subtle);padding:12px;border-radius:var(--radius-md);margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:0.85rem;color:var(--text-muted);">Total Kas/Bank Terpotong:</span>
                    <strong id="displayTotalDeduct" style="font-size:1.15rem;color:var(--danger);" class="text-mono">Rp 0</strong>
                </div>

                <div class="form-group">
                    <label class="form-label" for="depDate">Tanggal Pembayaran</label>
                    <input type="date" name="deposit_date" id="depDate" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="depNotes">Catatan / No Tiket Deposit</label>
                    <input type="text" name="notes" id="depNotes" class="form-control" placeholder="Contoh: Tiket deposit tiket #12345 BCA transfer">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('depositModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Konfirmasi Deposit</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDepositForSupplier(supId) {
    document.getElementById('depositSupplierId').value = supId;
    openModal('depositModal');
}

function calcTotalDeduct() {
    const amt = parseFloat(document.getElementById('depAmount').value || 0);
    const fee = parseFloat(document.getElementById('depFee').value || 0);
    const total = amt + fee;
    document.getElementById('displayTotalDeduct').textContent = 'Rp ' + total.toLocaleString('id-ID');
}
</script>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
