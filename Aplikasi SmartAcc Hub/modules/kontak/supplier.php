<?php
/**
 * SmartAcc Hub - Manajemen Data Supplier
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();
$currentUser = current_user();

// Handle Add / Edit Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_supplier'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $notes = trim($_POST['notes'] ?? '');

    if (!empty($name)) {
        if (!empty($_POST['sup_id'])) {
            $supId = (int)$_POST['sup_id'];
            $stmt = $db->prepare("UPDATE suppliers SET name = ?, phone = ?, email = ?, status = ?, notes = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $email, $status, $notes, $supId]);
            log_activity('UPDATE_SUPPLIER', "Memperbarui data supplier {$name}");
            set_flash('success', "Data supplier {$name} berhasil diperbarui.");
        } else {
            $initialBal = (float)($_POST['initial_balance'] ?? 0);
            $stmt = $db->prepare("INSERT INTO suppliers (name, phone, email, balance, status, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $email, $initialBal, $status, $notes]);
            log_activity('ADD_SUPPLIER', "Menambahkan supplier baru {$name}");
            set_flash('success', "Supplier baru {$name} berhasil ditambahkan.");
        }
    }
    header('Location: ' . BASE_URL . '/index.php?page=supplier');
    exit;
}

$suppliers = $db->query("SELECT * FROM suppliers ORDER BY id ASC")->fetchAll();

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="card-box">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-truck-loading" style="color:var(--primary);"></i>
            Data Supplier Top Up Game & Saldo Deposit
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('supplierModal')">
            <i class="fas fa-plus"></i> Tambah Supplier Baru
        </button>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Nama Supplier</th>
                    <th>No. Kontak / WA</th>
                    <th>Email API / Support</th>
                    <th style="text-align:right;">Saldo Deposit Terkini</th>
                    <th>Status Kerjasama</th>
                    <th>Catatan & Integrasi</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suppliers as $s): ?>
                    <tr>
                        <td>
                            <strong style="font-size:0.95rem;"><?= htmlspecialchars($s['name']) ?></strong>
                        </td>
                        <td>
                            <?php if (!empty($s['phone'])): ?>
                                <span class="status-badge badge-secondary"><i class="fas fa-phone me-1"></i> <?= htmlspecialchars($s['phone']) ?></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--text-muted);"><?= htmlspecialchars($s['email'] ?: '-') ?></td>
                        <td style="text-align:right;" class="text-mono font-weight-bold">
                            <span style="font-size:1.1rem;color:<?= $s['balance'] < 1000000 ? 'var(--warning)' : 'var(--primary)' ?>;">
                                <?= format_rupiah($s['balance']) ?>
                            </span>
                        </td>
                        <td><?= render_status_badge($s['status']) ?></td>
                        <td style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($s['notes'] ?: '-') ?></td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <a href="<?= BASE_URL ?>/index.php?page=deposit" class="btn btn-success btn-sm" title="Isi Saldo Deposit">
                                    <i class="fas fa-plus"></i> Deposit
                                </a>
                                <button type="button" class="btn btn-outline btn-sm" onclick='editSupplier(<?= json_encode($s) ?>)' title="Edit Supplier">
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

<!-- Modal Tambah/Edit Supplier -->
<div class="modal-overlay" id="supplierModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title" id="supModalTitle"><i class="fas fa-truck-loading me-2"></i> Tambah Supplier Baru</h3>
            <button type="button" class="btn-close" onclick="closeModal('supplierModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="save_supplier" value="1">
            <input type="hidden" name="sup_id" id="formSupId" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="supName">Nama Supplier / Server API *</label>
                    <input type="text" name="name" id="supName" class="form-control" placeholder="Contoh: Digiflazz VIP / Tokovoucher" required>
                </div>
                <div style="display:grid;grid-template-columns: 1fr 1fr;gap:14px;">
                    <div class="form-group">
                        <label class="form-label" for="supPhone">No. Kontak / WA Support</label>
                        <input type="text" name="phone" id="supPhone" class="form-control" placeholder="08xxxxxxxxxx">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="supEmail">Email Support / API</label>
                        <input type="email" name="email" id="supEmail" class="form-control" placeholder="api@supplier.com">
                    </div>
                </div>
                <div class="form-group" id="initialBalanceGroup">
                    <label class="form-label" for="supInitBal">Saldo Deposit Awal (Rp)</label>
                    <input type="number" step="1000" name="initial_balance" id="supInitBal" class="form-control" placeholder="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label" for="supStatus">Status Kerjasama</label>
                    <select name="status" id="supStatus" class="form-select">
                        <option value="active">Aktif (Digunakan)</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="supNotes">Catatan / Keterangan Integrasi</label>
                    <textarea name="notes" id="supNotes" class="form-control" rows="2" placeholder="Catatan format API atau rekening pembayaran deposit..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('supplierModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Supplier</button>
            </div>
        </form>
    </div>
</div>

<script>
function editSupplier(s) {
    document.getElementById('supModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i> Edit Data Supplier';
    document.getElementById('formSupId').value = s.id;
    document.getElementById('supName').value = s.name;
    document.getElementById('supPhone').value = s.phone || '';
    document.getElementById('supEmail').value = s.email || '';
    document.getElementById('supStatus').value = s.status;
    document.getElementById('supNotes').value = s.notes || '';
    document.getElementById('initialBalanceGroup').style.display = 'none';
    openModal('supplierModal');
}
</script>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
