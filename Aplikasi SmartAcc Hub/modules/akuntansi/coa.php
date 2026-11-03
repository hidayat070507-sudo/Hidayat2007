<?php
/**
 * SmartAcc Hub - Chart of Accounts (Daftar Bagan Akun)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();
$currentUser = current_user();

// Handle Add/Edit Account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_account'])) {
    $code = trim($_POST['code']);
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $normalBalance = $_POST['normal_balance'];
    $isCashBank = isset($_POST['is_cash_bank']) ? 1 : 0;
    $desc = trim($_POST['description'] ?? '');

    if (!empty($code) && !empty($name)) {
        if (!empty($_POST['account_id'])) {
            $accId = (int)$_POST['account_id'];
            $stmt = $db->prepare("UPDATE accounts SET code = ?, name = ?, type = ?, normal_balance = ?, is_cash_bank = ?, description = ? WHERE id = ?");
            $stmt->execute([$code, $name, $type, $normalBalance, $isCashBank, $desc, $accId]);
            log_activity('UPDATE_COA', "Memperbarui akun {$code} - {$name}");
            set_flash('success', "Akun {$code} - {$name} berhasil diperbarui.");
        } else {
            // Check code duplicate
            $chk = $db->prepare("SELECT id FROM accounts WHERE code = ?");
            $chk->execute([$code]);
            if ($chk->fetch()) {
                set_flash('danger', "Kode akun {$code} sudah digunakan!");
            } else {
                $stmt = $db->prepare("INSERT INTO accounts (code, name, type, normal_balance, is_cash_bank, description) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$code, $name, $type, $normalBalance, $isCashBank, $desc]);
                log_activity('ADD_COA', "Menambahkan akun baru {$code} - {$name}");
                set_flash('success', "Akun baru {$code} - {$name} berhasil ditambahkan.");
            }
        }
    }
    header('Location: ' . BASE_URL . '/index.php?page=coa');
    exit;
}

$accounts = $db->query("SELECT * FROM accounts ORDER BY code ASC")->fetchAll();

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="card-box">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-list-ol" style="color:var(--primary);"></i>
            Bagan Akun (Chart of Accounts / COA)
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('accountModal')">
            <i class="fas fa-plus"></i> Tambah Akun Baru
        </button>
    </div>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Kode Akun</th>
                    <th>Nama Akun</th>
                    <th>Kategori / Tipe Akun</th>
                    <th>Saldo Normal</th>
                    <th>Kas / Bank?</th>
                    <th>Saldo Berjalan</th>
                    <th>Keterangan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $a): ?>
                    <?php 
                    $bal = get_account_balance($db, (int)$a['id']);
                    $typeClass = 'badge-secondary';
                    if ($a['type'] === 'ASET') $typeClass = 'badge-info';
                    if ($a['type'] === 'KEWAJIBAN') $typeClass = 'badge-warning';
                    if ($a['type'] === 'EKUITAS') $typeClass = 'badge-primary';
                    if ($a['type'] === 'PENDAPATAN') $typeClass = 'badge-success';
                    if (strpos($a['type'], 'BEBAN') !== false) $typeClass = 'badge-danger';
                    ?>
                    <tr>
                        <td class="text-mono"><strong><?= htmlspecialchars($a['code']) ?></strong></td>
                        <td><strong><?= htmlspecialchars($a['name']) ?></strong></td>
                        <td><span class="status-badge <?= $typeClass ?>"><?= htmlspecialchars($a['type']) ?></span></td>
                        <td>
                            <span style="font-size:0.75rem;font-weight:700;" class="text-mono">
                                <?= htmlspecialchars($a['normal_balance']) ?>
                            </span>
                        </td>
                        <td>
                            <?= $a['is_cash_bank'] ? '<span class="status-badge badge-success"><i class="fas fa-check"></i> Ya</span>' : '-' ?>
                        </td>
                        <td class="text-mono font-weight-bold" style="font-size:0.95rem;">
                            <?= format_rupiah($bal) ?>
                        </td>
                        <td style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($a['description'] ?: '-') ?></td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <a href="<?= BASE_URL ?>/index.php?page=buku_besar&account_id=<?= $a['id'] ?>" class="btn btn-outline btn-sm" title="Buku Besar">
                                    <i class="fas fa-book-open"></i>
                                </a>
                                <button type="button" class="btn btn-outline btn-sm" onclick='editAccount(<?= json_encode($a) ?>)' title="Edit Akun">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah/Edit Akun -->
<div class="modal-overlay" id="accountModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title" id="accountModalTitle"><i class="fas fa-plus-circle me-2"></i> Tambah Akun Baru</h3>
            <button type="button" class="btn-close" onclick="closeModal('accountModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="save_account" value="1">
            <input type="hidden" name="account_id" id="formAccId" value="">

            <div class="modal-body">
                <div style="display:grid;grid-template-columns: 120px 1fr;gap:14px;">
                    <div class="form-group">
                        <label class="form-label" for="accCode">Kode Akun *</label>
                        <input type="text" name="code" id="accCode" class="form-control text-mono" placeholder="1106" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="accName">Nama Akun *</label>
                        <input type="text" name="name" id="accName" class="form-control" placeholder="Contoh: Kas Kecil Cabang" required>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns: 1fr 1fr;gap:14px;">
                    <div class="form-group">
                        <label class="form-label" for="accType">Kelompok / Tipe Akun *</label>
                        <select name="type" id="accType" class="form-select" required onchange="autoSelectNormalBalance()">
                            <option value="ASET">1 - ASET (Harta)</option>
                            <option value="KEWAJIBAN">2 - KEWAJIBAN (Utang)</option>
                            <option value="EKUITAS">3 - EKUITAS (Modal)</option>
                            <option value="PENDAPATAN">4 - PENDAPATAN (Penjualan)</option>
                            <option value="BEBAN_HPP">5 - BEBAN POKOK (HPP)</option>
                            <option value="BEBAN_OPERASIONAL">6 - BEBAN OPERASIONAL</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="accNormalBalance">Saldo Normal *</label>
                        <select name="normal_balance" id="accNormalBalance" class="form-select" required>
                            <option value="DEBIT">DEBIT</option>
                            <option value="KREDIT">KREDIT</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top:10px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_cash_bank" id="accIsCashBank" value="1">
                        <span style="font-weight:600;font-size:0.88rem;">Termasuk Golongan Kas & Bank (Likuid)</span>
                    </label>
                    <div class="input-hint" style="margin-left:22px;">Centang jika akun ini berupa kas fisik atau rekening bank/e-wallet untuk pembayaran transaksi.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="accDesc">Keterangan / Fungsi Akun</label>
                    <textarea name="description" id="accDesc" class="form-control" rows="2" placeholder="Catatan kegunaan akun ini..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('accountModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Akun</button>
            </div>
        </form>
    </div>
</div>

<script>
function autoSelectNormalBalance() {
    const type = document.getElementById('accType').value;
    const nb = document.getElementById('accNormalBalance');
    if (type === 'ASET' || type === 'BEBAN_HPP' || type === 'BEBAN_OPERASIONAL') {
        nb.value = 'DEBIT';
    } else {
        nb.value = 'KREDIT';
    }
}

function editAccount(acc) {
    document.getElementById('accountModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i> Edit Bagan Akun';
    document.getElementById('formAccId').value = acc.id;
    document.getElementById('accCode').value = acc.code;
    document.getElementById('accName').value = acc.name;
    document.getElementById('accType').value = acc.type;
    document.getElementById('accNormalBalance').value = acc.normal_balance;
    document.getElementById('accIsCashBank').checked = (acc.is_cash_bank == 1);
    document.getElementById('accDesc').value = acc.description || '';
    openModal('accountModal');
}
</script>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
