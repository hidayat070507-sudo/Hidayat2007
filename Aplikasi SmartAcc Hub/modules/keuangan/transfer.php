<?php
/**
 * SmartAcc Hub - Transfer Antar Rekening Kas & Bank
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();
$currentUser = current_user();

// Handle New Transfer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_transfer'])) {
    $fromAcc = (int)$_POST['from_account_id'];
    $toAcc = (int)$_POST['to_account_id'];
    $amount = (float)$_POST['amount'];
    $fee = (float)($_POST['fee'] ?? 0);
    $transferDate = $_POST['transfer_date'] ?: date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');

    if ($fromAcc > 0 && $toAcc > 0 && $fromAcc !== $toAcc && $amount > 0) {
        $prefix = 'TRF-' . date('Ymd') . '-';
        $stmtLast = $db->prepare("SELECT transfer_no FROM transfers WHERE transfer_no LIKE ? ORDER BY id DESC LIMIT 1");
        $stmtLast->execute([$prefix . '%']);
        $last = $stmtLast->fetchColumn();
        $trfNo = $prefix . str_pad($last ? ((int)substr($last, strlen($prefix)) + 1) : 1, 3, '0', STR_PAD_LEFT);

        // 1. Insert transfer
        $stmt = $db->prepare("INSERT INTO transfers (transfer_no, from_account_id, to_account_id, amount, fee, transfer_date, notes, created_by)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$trfNo, $fromAcc, $toAcc, $amount, $fee, $transferDate, $notes, $currentUser['username']]);

        // 2. Double-Entry Journal:
        // Debit: To Account ($amount)
        // Debit: Beban Admin Bank ($fee)
        // Kredit: From Account ($amount + $fee)
        $accFee = $db->query("SELECT id FROM accounts WHERE code='6104'")->fetchColumn();
        $totalOut = $amount + $fee;

        $fromName = $db->query("SELECT name FROM accounts WHERE id = $fromAcc")->fetchColumn();
        $toName = $db->query("SELECT name FROM accounts WHERE id = $toAcc")->fetchColumn();

        $jno = generate_journal_no();
        $items = [
            ['account_id' => $toAcc, 'debit' => $amount, 'credit' => 0, 'memo' => "Penerimaan transfer dari {$fromName}"],
            ['account_id' => $fromAcc, 'debit' => 0, 'credit' => $totalOut, 'memo' => "Pengeluaran transfer ke {$toName}"]
        ];
        if ($fee > 0) {
            $items[] = ['account_id' => $accFee, 'debit' => $fee, 'credit' => 0, 'memo' => "Biaya admin transfer {$trfNo}"];
        }

        create_journal_entry($db, $jno, $transferDate, 'TRANSFER', $trfNo, "Transfer Rekening {$fromName} ke {$toName} ({$trfNo})", $items, $currentUser['username']);

        log_activity('TRANSFER_FUNDS', "Transfer " . format_rupiah($amount) . " dari {$fromName} ke {$toName}");
        set_flash('success', "Transfer {$trfNo} sebesar " . format_rupiah($amount) . " berhasil diproses!");
        header('Location: ' . BASE_URL . '/index.php?page=transfer');
        exit;
    } else if ($fromAcc === $toAcc) {
        set_flash('danger', "Rekening asal dan rekening tujuan tidak boleh sama!");
    }
}

// Fetch Accounts and Transfer History
$cashAccounts = $db->query("SELECT id, code, name FROM accounts WHERE is_cash_bank = 1 ORDER BY code ASC")->fetchAll();
$transfers = $db->query("SELECT t.*, fa.name as from_name, ta.name as to_name 
                         FROM transfers t 
                         JOIN accounts fa ON t.from_account_id = fa.id 
                         JOIN accounts ta ON t.to_account_id = ta.id 
                         ORDER BY t.id DESC")->fetchAll();

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="card-box">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-exchange-alt" style="color:var(--primary);"></i>
            Mutasi Transfer Antar Rekening Kas & Bank
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('transferModal')">
            <i class="fas fa-plus"></i> Transfer Baru
        </button>
    </div>

    <!-- Accounts Balance Overview Row -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;margin-bottom:24px;">
        <?php foreach ($cashAccounts as $ca): ?>
            <?php $bal = get_account_balance($db, (int)$ca['id']); ?>
            <div style="padding:16px;background:var(--bg-card-subtle);border-radius:var(--radius-md);border:1px solid var(--border-color);">
                <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;font-weight:600;"><?= htmlspecialchars($ca['name']) ?></div>
                <div style="font-size:1.3rem;font-weight:800;color:var(--primary);font-family:'JetBrains Mono', monospace;margin-top:6px;">
                    <?= format_rupiah($bal) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>No Transfer</th>
                    <th>Tanggal</th>
                    <th>Dari Rekening (Asal)</th>
                    <th>Ke Rekening (Tujuan)</th>
                    <th>Nominal Transfer</th>
                    <th>Biaya Admin</th>
                    <th>Catatan</th>
                    <th>Pencatat</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transfers)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:35px;color:var(--text-muted);">Belum ada riwayat mutasi transfer antar rekening.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transfers as $tr): ?>
                        <tr>
                            <td><strong class="text-mono"><?= htmlspecialchars($tr['transfer_no']) ?></strong></td>
                            <td style="font-size:0.82rem;color:var(--text-muted);"><?= format_date($tr['transfer_date']) ?></td>
                            <td><span class="status-badge badge-danger"><?= htmlspecialchars($tr['from_name']) ?></span></td>
                            <td><span class="status-badge badge-success"><?= htmlspecialchars($tr['to_name']) ?></span></td>
                            <td class="text-mono font-weight-bold"><?= format_rupiah($tr['amount']) ?></td>
                            <td class="text-mono" style="color:var(--text-muted);"><?= format_rupiah($tr['fee']) ?></td>
                            <td><?= htmlspecialchars($tr['notes'] ?: '-') ?></td>
                            <td style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($tr['created_by']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Form Transfer -->
<div class="modal-overlay" id="transferModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-exchange-alt me-2"></i> Transfer Antar Rekening</h3>
            <button type="button" class="btn-close" onclick="closeModal('transferModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="save_transfer" value="1">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="fromAccount">Dari Rekening / Kas (Asal) *</label>
                    <select name="from_account_id" id="fromAccount" class="form-select" required>
                        <?php foreach ($cashAccounts as $ca): ?>
                            <option value="<?= $ca['id'] ?>"><?= htmlspecialchars($ca['name']) ?> (Saldo: <?= format_rupiah(get_account_balance($db, (int)$ca['id'])) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="toAccount">Ke Rekening / Kas (Tujuan) *</label>
                    <select name="to_account_id" id="toAccount" class="form-select" required>
                        <?php foreach ($cashAccounts as $idx => $ca): ?>
                            <option value="<?= $ca['id'] ?>" <?= $idx === 1 ? 'selected' : '' ?>><?= htmlspecialchars($ca['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="form-group">
                        <label class="form-label" for="trfAmount">Nominal Transfer (Rp) *</label>
                        <input type="number" step="1000" name="amount" id="trfAmount" class="form-control" placeholder="1000000" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="trfFee">Biaya Admin (Bila Ada)</label>
                        <input type="number" step="100" name="fee" id="trfFee" class="form-control" placeholder="0" value="0">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="trfDate">Tanggal Transfer</label>
                    <input type="date" name="transfer_date" id="trfDate" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="trfNotes">Catatan</label>
                    <input type="text" name="notes" id="trfNotes" class="form-control" placeholder="Contoh: Setor kas harian kasir toko ke rekening BCA">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('transferModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Proses Transfer</button>
            </div>
        </form>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
