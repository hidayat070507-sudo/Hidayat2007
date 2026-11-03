<?php
/**
 * SmartAcc Hub - Jurnal Umum (General Journal)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();
$currentUser = current_user();

// Handle Manual Journal Entry Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_manual_journal'])) {
    $entryDate = $_POST['entry_date'] ?: date('Y-m-d');
    $description = trim($_POST['description']);
    $accounts = $_POST['account_id'] ?? [];
    $debits = $_POST['debit'] ?? [];
    $credits = $_POST['credit'] ?? [];
    $memos = $_POST['memo'] ?? [];

    $totalDebit = 0;
    $totalCredit = 0;
    $items = [];

    for ($i = 0; $i < count($accounts); $i++) {
        $accId = (int)$accounts[$i];
        $deb = (float)($debits[$i] ?? 0);
        $crd = (float)($credits[$i] ?? 0);
        $memo = trim($memos[$i] ?? '');

        if ($accId > 0 && ($deb > 0 || $crd > 0)) {
            $totalDebit += $deb;
            $totalCredit += $crd;
            $items[] = [
                'account_id' => $accId,
                'debit' => $deb,
                'credit' => $crd,
                'memo' => $memo ?: $description
            ];
        }
    }

    if (count($items) >= 2 && abs($totalDebit - $totalCredit) < 0.01) {
        $jno = generate_journal_no();
        create_journal_entry($db, $jno, $entryDate, 'MANUAL', $jno, $description, $items, $currentUser['username']);
        log_activity('MANUAL_JOURNAL', "Mencatat jurnal umum manual {$jno} senilai " . format_rupiah($totalDebit));
        set_flash('success', "Jurnal manual {$jno} berhasil disimpan secara seimbang (Balance).");
    } else {
        set_flash('danger', "Jurnal gagal disimpan: Total Debit (" . format_rupiah($totalDebit) . ") dan Total Kredit (" . format_rupiah($totalCredit) . ") tidak seimbang atau kurang dari 2 akun.");
    }

    header('Location: ' . BASE_URL . '/index.php?page=jurnal');
    exit;
}

// Date Filter
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$sql = "SELECT je.* FROM journal_entries je 
        WHERE je.entry_date BETWEEN ? AND ? 
        ORDER BY je.entry_date DESC, je.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute([$startDate, $endDate]);
$journals = $stmt->fetchAll();

// Pre-fetch all items for these journals
$journalIds = array_column($journals, 'id');
$itemsByJournal = [];

if (!empty($journalIds)) {
    $inClause = implode(',', array_map('intval', $journalIds));
    $stmtItems = $db->query("SELECT ji.*, a.code as acc_code, a.name as acc_name 
                             FROM journal_items ji 
                             JOIN accounts a ON ji.account_id = a.id 
                             WHERE ji.journal_id IN ($inClause) 
                             ORDER BY ji.debit DESC, ji.id ASC");
    $allItems = $stmtItems->fetchAll();
    foreach ($allItems as $it) {
        $itemsByJournal[$it['journal_id']][] = $it;
    }
}

// All Accounts for Manual Journal Dropdown
$allAccounts = $db->query("SELECT id, code, name FROM accounts ORDER BY code ASC")->fetchAll();

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="card-box">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-book" style="color:var(--primary);"></i>
            Jurnal Umum (Double-Entry Bookkeeping)
        </div>
        <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('manualJournalModal')">
                <i class="fas fa-plus"></i> Input Jurnal Manual
            </button>
            <a href="<?= BASE_URL ?>/index.php?page=export&type=jurnal&start=<?= $startDate ?>&end=<?= $endDate ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-file-excel"></i> Ekspor CSV
            </a>
        </div>
    </div>

    <!-- Filter Form -->
    <form method="GET" action="" style="display:flex;gap:12px;align-items:center;margin-bottom:20px;flex-wrap:wrap;">
        <input type="hidden" name="page" value="jurnal">
        <div style="display:flex;align-items:center;gap:6px;">
            <label style="font-size:0.82rem;font-weight:600;color:var(--text-muted);">Periode:</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="form-control" style="width:auto;">
            <span style="color:var(--text-muted);">&mdash;</span>
            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="form-control" style="width:auto;">
        </div>
        <button type="submit" class="btn btn-outline"><i class="fas fa-search"></i> Tampilkan</button>
    </form>

    <!-- Journals Table -->
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th style="width:130px;">Tanggal / No</th>
                    <th>Keterangan / Transaksi</th>
                    <th>Akun & Kode Rekening</th>
                    <th style="text-align:right;width:150px;">Debit</th>
                    <th style="text-align:right;width:150px;">Kredit</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $grandDebit = 0;
                $grandCredit = 0;
                ?>
                <?php if (empty($journals)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted);">Tidak ada catatan jurnal pada rentang periode ini.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($journals as $j): ?>
                        <?php 
                        $items = $itemsByJournal[$j['id']] ?? []; 
                        $first = true;
                        ?>
                        <?php foreach ($items as $idx => $item): ?>
                            <?php 
                            $grandDebit += (float)$item['debit'];
                            $grandCredit += (float)$item['credit'];
                            ?>
                            <tr style="<?= $idx === count($items) - 1 ? 'border-bottom: 2px solid var(--border-color);' : '' ?>">
                                <?php if ($first): ?>
                                    <td rowspan="<?= count($items) ?>" style="vertical-align:top;background:var(--bg-card-subtle);">
                                        <div style="font-weight:700;font-size:0.85rem;"><?= format_date($j['entry_date']) ?></div>
                                        <div class="text-mono" style="font-size:0.75rem;color:var(--primary);"><?= htmlspecialchars($j['journal_no']) ?></div>
                                        <span class="status-badge badge-secondary" style="font-size:0.65rem;margin-top:4px;">
                                            <?= htmlspecialchars($j['reference_type']) ?>
                                        </span>
                                    </td>
                                    <td rowspan="<?= count($items) ?>" style="vertical-align:top;">
                                        <strong><?= htmlspecialchars($j['description']) ?></strong><br>
                                        <small style="color:var(--text-muted);">Ref: <?= htmlspecialchars($j['reference_id'] ?: '-') ?> | Oleh: <?= htmlspecialchars($j['created_by']) ?></small>
                                    </td>
                                    <?php $first = false; ?>
                                <?php endif; ?>
                                <td style="padding-left: <?= (float)$item['credit'] > 0 ? '30px' : '14px' ?>;">
                                    <span class="text-mono" style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($item['acc_code']) ?></span> &nbsp;
                                    <strong><?= htmlspecialchars($item['acc_name']) ?></strong>
                                    <?php if (!empty($item['memo']) && $item['memo'] !== $j['description']): ?>
                                        <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($item['memo']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;" class="text-mono">
                                    <?= (float)$item['debit'] > 0 ? format_rupiah($item['debit']) : '-' ?>
                                </td>
                                <td style="text-align:right;" class="text-mono">
                                    <?= (float)$item['credit'] > 0 ? format_rupiah($item['credit']) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:var(--bg-card-subtle);font-weight:800;font-size:1rem;">
                    <td colspan="3" style="text-align:right;padding:14px;">TOTAL JURNAL:</td>
                    <td style="text-align:right;color:var(--primary);" class="text-mono"><?= format_rupiah($grandDebit) ?></td>
                    <td style="text-align:right;color:var(--primary);" class="text-mono"><?= format_rupiah($grandCredit) ?></td>
                </tr>
                <tr style="background:var(--bg-card);">
                    <td colspan="5" style="text-align:right;padding:8px 14px;">
                        <?php if (abs($grandDebit - $grandCredit) < 0.01): ?>
                            <span class="status-badge badge-success"><i class="fas fa-check-double"></i> SEIMBANG (DEBIT = KREDIT BALANCE)</span>
                        <?php else: ?>
                            <span class="status-badge badge-danger"><i class="fas fa-exclamation-triangle"></i> TIDAK SEIMBANG! Selisih: <?= format_rupiah(abs($grandDebit - $grandCredit)) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Modal Input Jurnal Manual -->
<div class="modal-overlay" id="manualJournalModal">
    <div class="modal-container modal-lg">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-book me-2"></i> Buat Catatan Jurnal Manual</h3>
            <button type="button" class="btn-close" onclick="closeModal('manualJournalModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="save_manual_journal" value="1">
            <div class="modal-body">
                <div style="display:grid;grid-template-columns: 180px 1fr;gap:14px;margin-bottom:16px;">
                    <div class="form-group">
                        <label class="form-label" for="manualDate">Tanggal Jurnal *</label>
                        <input type="date" name="entry_date" id="manualDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="manualDesc">Keterangan Transaksi Jurnal *</label>
                        <input type="text" name="description" id="manualDesc" class="form-control" placeholder="Contoh: Pencatatan penyusutan komputer atau setoran modal" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Rincian Baris Jurnal (Minimal 2 Akun)</label>
                    <table style="width:100%;border-collapse:collapse;" id="journalRowsTable">
                        <thead>
                            <tr style="font-size:0.78rem;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border-color);">
                                <th style="padding:6px;width:40%;">Pilih Akun</th>
                                <th style="padding:6px;width:25%;">Debit (Rp)</th>
                                <th style="padding:6px;width:25%;">Kredit (Rp)</th>
                                <th style="padding:6px;width:10%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="journalRowsBody">
                            <!-- Row 1 -->
                            <tr>
                                <td style="padding:6px;">
                                    <select name="account_id[]" class="form-select" required>
                                        <option value="">-- Pilih Akun --</option>
                                        <?php foreach ($allAccounts as $acc): ?>
                                            <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['code']) ?> - <?= htmlspecialchars($acc['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td style="padding:6px;">
                                    <input type="number" step="100" name="debit[]" class="form-control deb-input" placeholder="0" value="0" oninput="validateBalance()">
                                </td>
                                <td style="padding:6px;">
                                    <input type="number" step="100" name="credit[]" class="form-control crd-input" placeholder="0" value="0" oninput="validateBalance()">
                                </td>
                                <td style="padding:6px;text-align:center;">-</td>
                            </tr>
                            <!-- Row 2 -->
                            <tr>
                                <td style="padding:6px;">
                                    <select name="account_id[]" class="form-select" required>
                                        <option value="">-- Pilih Akun --</option>
                                        <?php foreach ($allAccounts as $acc): ?>
                                            <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['code']) ?> - <?= htmlspecialchars($acc['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td style="padding:6px;">
                                    <input type="number" step="100" name="debit[]" class="form-control deb-input" placeholder="0" value="0" oninput="validateBalance()">
                                </td>
                                <td style="padding:6px;">
                                    <input type="number" step="100" name="credit[]" class="form-control crd-input" placeholder="0" value="0" oninput="validateBalance()">
                                </td>
                                <td style="padding:6px;text-align:center;">-</td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-outline btn-sm" style="margin-top:10px;" onclick="addJournalRow()">
                        <i class="fas fa-plus"></i> Tambah Baris Akun
                    </button>
                </div>

                <!-- Live Balance Checker Box -->
                <div style="background:var(--bg-card-subtle);border-radius:var(--radius-md);padding:14px;display:flex;justify-content:space-between;align-items:center;margin-top:14px;">
                    <div>
                        <div style="font-size:0.75rem;color:var(--text-muted);">Total Debit:</div>
                        <strong class="text-mono" id="liveDebTotal">Rp 0</strong>
                    </div>
                    <div>
                        <div style="font-size:0.75rem;color:var(--text-muted);">Total Kredit:</div>
                        <strong class="text-mono" id="liveCrdTotal">Rp 0</strong>
                    </div>
                    <div>
                        <span id="balanceBadge" class="status-badge badge-warning">Belum Balance</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('manualJournalModal')">Batal</button>
                <button type="submit" class="btn btn-primary" id="btnSaveJournal" disabled>Simpan Jurnal</button>
            </div>
        </form>
    </div>
</div>

<script>
const accountsOptions = `
    <option value="">-- Pilih Akun --</option>
    <?php foreach ($allAccounts as $acc): ?>
        <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['code']) ?> - <?= htmlspecialchars($acc['name']) ?></option>
    <?php endforeach; ?>
`;

function addJournalRow() {
    const tbody = document.getElementById('journalRowsBody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td style="padding:6px;">
            <select name="account_id[]" class="form-select" required>
                \${accountsOptions}
            </select>
        </td>
        <td style="padding:6px;">
            <input type="number" step="100" name="debit[]" class="form-control deb-input" placeholder="0" value="0" oninput="validateBalance()">
        </td>
        <td style="padding:6px;">
            <input type="number" step="100" name="credit[]" class="form-control crd-input" placeholder="0" value="0" oninput="validateBalance()">
        </td>
        <td style="padding:6px;text-align:center;">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();validateBalance();"><i class="fas fa-trash"></i></button>
        </td>
    `;
    tbody.appendChild(tr);
}

function validateBalance() {
    let debTotal = 0;
    let crdTotal = 0;

    document.querySelectorAll('.deb-input').forEach(i => debTotal += parseFloat(i.value || 0));
    document.querySelectorAll('.crd-input').forEach(i => crdTotal += parseFloat(i.value || 0));

    document.getElementById('liveDebTotal').textContent = 'Rp ' + debTotal.toLocaleString('id-ID');
    document.getElementById('liveCrdTotal').textContent = 'Rp ' + crdTotal.toLocaleString('id-ID');

    const badge = document.getElementById('balanceBadge');
    const btn = document.getElementById('btnSaveJournal');

    if (debTotal > 0 && Math.abs(debTotal - crdTotal) < 0.01) {
        badge.className = 'status-badge badge-success';
        badge.innerHTML = '<i class="fas fa-check"></i> Seimbang (Balance)';
        btn.disabled = false;
    } else {
        badge.className = 'status-badge badge-danger';
        badge.innerHTML = '<i class="fas fa-times"></i> Belum Seimbang (Selisih: Rp ' + Math.abs(debTotal - crdTotal).toLocaleString('id-ID') + ')';
        btn.disabled = true;
    }
}
</script>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
