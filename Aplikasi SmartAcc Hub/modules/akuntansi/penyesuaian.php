<?php
/**
 * SmartAcc Hub - Jurnal Penyesuaian (Adjusting Entries)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();
$currentUser = current_user();

// Handle New Adjusting Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_adjustment'])) {
    $adjDate = $_POST['adj_date'] ?: date('Y-m-d');
    $description = trim($_POST['description']);
    $debitAccId = (int)$_POST['debit_account_id'];
    $creditAccId = (int)$_POST['credit_account_id'];
    $amount = (float)$_POST['amount'];

    if ($debitAccId > 0 && $creditAccId > 0 && $debitAccId !== $creditAccId && $amount > 0) {
        $jno = generate_journal_no();
        $items = [
            ['account_id' => $debitAccId, 'debit' => $amount, 'credit' => 0, 'memo' => $description],
            ['account_id' => $creditAccId, 'debit' => 0, 'credit' => $amount, 'memo' => $description]
        ];

        create_journal_entry($db, $jno, $adjDate, 'PENYESUAIAN', 'ADJ-' . date('Ymd'), "Penyesuaian: " . $description, $items, $currentUser['username']);
        log_activity('ADJUSTING_ENTRY', "Mencatat jurnal penyesuaian {$jno} ({$description}) senilai " . format_rupiah($amount));
        set_flash('success', "Jurnal penyesuaian {$jno} berhasil dicatat!");
        header('Location: ' . BASE_URL . '/index.php?page=penyesuaian');
        exit;
    } else {
        set_flash('danger', "Pastikan akun debit dan kredit berbeda dan nominal lebih dari 0.");
    }
}

// Fetch all adjusting entries
$stmt = $db->query("SELECT je.*, 
    (SELECT SUM(debit) FROM journal_items WHERE journal_id = je.id) as total_amount 
    FROM journal_entries je 
    WHERE je.reference_type = 'PENYESUAIAN' 
    ORDER BY je.entry_date DESC, je.id DESC");
$adjustments = $stmt->fetchAll();

$allAccounts = $db->query("SELECT id, code, name, type FROM accounts ORDER BY code ASC")->fetchAll();

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="card-box">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-sliders-h" style="color:var(--primary);"></i>
            Jurnal Penyesuaian (Adjusting Entries)
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('adjustModal')">
            <i class="fas fa-plus"></i> Input Jurnal Penyesuaian
        </button>
    </div>

    <p style="color:var(--text-muted);font-size:0.88rem;margin-bottom:20px;">
        Jurnal penyesuaian digunakan untuk mencatat pengakuan beban atau pendapatan pada akhir periode akuntansi, seperti beban penyusutan peralatan, sewa dibayar di muka, atau penyesuaian saldo akhir.
    </p>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>No Jurnal</th>
                    <th>Tanggal Penyesuaian</th>
                    <th>Keterangan / Alasan Penyesuaian</th>
                    <th>Rincian Debit & Kredit</th>
                    <th style="text-align:right;">Nominal Total</th>
                    <th>Pencatat</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($adjustments)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:35px;color:var(--text-muted);">Belum ada catatan jurnal penyesuaian.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($adjustments as $adj): ?>
                        <?php
                        $stmtItems = $db->prepare("SELECT ji.*, a.code, a.name 
                                                   FROM journal_items ji 
                                                   JOIN accounts a ON ji.account_id = a.id 
                                                   WHERE ji.journal_id = ? 
                                                   ORDER BY ji.debit DESC");
                        $stmtItems->execute([$adj['id']]);
                        $items = $stmtItems->fetchAll();
                        ?>
                        <tr>
                            <td><strong class="text-mono" style="color:var(--primary);"><?= htmlspecialchars($adj['journal_no']) ?></strong></td>
                            <td style="font-size:0.82rem;color:var(--text-muted);"><?= format_date($adj['entry_date']) ?></td>
                            <td><strong><?= htmlspecialchars($adj['description']) ?></strong></td>
                            <td>
                                <?php foreach ($items as $it): ?>
                                    <div style="font-size:0.8rem;margin-bottom:2px;">
                                        <span class="text-mono" style="color:var(--text-muted);"><?= htmlspecialchars($it['code']) ?></span>
                                        <?= htmlspecialchars($it['name']) ?> :
                                        <?php if ((float)$it['debit'] > 0): ?>
                                            <span style="color:var(--primary);font-weight:600;">(D) <?= format_rupiah($it['debit']) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--success);font-weight:600;">(K) <?= format_rupiah($it['credit']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                            <td style="text-align:right;" class="text-mono font-weight-bold"><?= format_rupiah($adj['total_amount']) ?></td>
                            <td style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($adj['created_by']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Form Penyesuaian -->
<div class="modal-overlay" id="adjustModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-sliders-h me-2"></i> Buat Jurnal Penyesuaian</h3>
            <button type="button" class="btn-close" onclick="closeModal('adjustModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="save_adjustment" value="1">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="adjDate">Tanggal Penyesuaian *</label>
                    <input type="date" name="adj_date" id="adjDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="adjDesc">Keterangan Penyesuaian *</label>
                    <input type="text" name="description" id="adjDesc" class="form-control" placeholder="Contoh: Penyusutan komputer toko bulan berjalan" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="debitAcc">Akun yang Didebit (Beban / Aset) *</label>
                    <select name="debit_account_id" id="debitAcc" class="form-select" required>
                        <option value="">-- Pilih Akun Debit --</option>
                        <?php foreach ($allAccounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['code']) ?> - <?= htmlspecialchars($acc['name']) ?> (<?= $acc['type'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="creditAcc">Akun yang Dikredit (Aset / Akumulasi / Kewajiban) *</label>
                    <select name="credit_account_id" id="creditAcc" class="form-select" required>
                        <option value="">-- Pilih Akun Kredit --</option>
                        <?php foreach ($allAccounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['code']) ?> - <?= htmlspecialchars($acc['name']) ?> (<?= $acc['type'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="adjAmt">Nominal Penyesuaian (Rp) *</label>
                    <input type="number" step="100" name="amount" id="adjAmt" class="form-control" placeholder="100000" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('adjustModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Simpan Penyesuaian</button>
            </div>
        </form>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
