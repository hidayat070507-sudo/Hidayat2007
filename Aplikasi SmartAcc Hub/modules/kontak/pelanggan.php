<?php
/**
 * SmartAcc Hub - Manajemen Data Pelanggan
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();
$currentUser = current_user();

// Handle Add / Edit Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_customer'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!empty($name)) {
        if (!empty($_POST['cust_id'])) {
            $custId = (int)$_POST['cust_id'];
            $stmt = $db->prepare("UPDATE customers SET name = ?, phone = ?, email = ?, notes = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $email, $notes, $custId]);
            log_activity('UPDATE_CUSTOMER', "Memperbarui data pelanggan {$name}");
            set_flash('success', "Data pelanggan {$name} berhasil diperbarui.");
        } else {
            $stmt = $db->prepare("INSERT INTO customers (name, phone, email, notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $email, $notes]);
            log_activity('ADD_CUSTOMER', "Menambahkan pelanggan baru {$name}");
            set_flash('success', "Pelanggan baru {$name} berhasil ditambahkan.");
        }
    }
    header('Location: ' . BASE_URL . '/index.php?page=pelanggan');
    exit;
}

// Handle Delete Customer
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $db->prepare("DELETE FROM customers WHERE id = ?")->execute([$delId]);
    log_activity('DELETE_CUSTOMER', "Menghapus pelanggan ID {$delId}");
    set_flash('warning', "Data pelanggan berhasil dihapus.");
    header('Location: ' . BASE_URL . '/index.php?page=pelanggan');
    exit;
}

// Fetch Customers with their total orders and total spent
$sql = "SELECT c.*, 
            COUNT(o.id) as order_count,
            COALESCE(SUM(CASE WHEN o.order_status = 'BERHASIL' THEN o.sell_price ELSE 0 END), 0) as total_spent,
            COALESCE((SELECT SUM(amount - paid_amount) FROM debts WHERE contact_name = c.name AND type = 'PIUTANG' AND status != 'LUNAS'), 0) as piutang_amount
        FROM customers c
        LEFT JOIN topup_orders o ON c.id = o.customer_id
        GROUP BY c.id
        ORDER BY total_spent DESC";
$customers = $db->query($sql)->fetchAll();

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="card-box">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-users" style="color:var(--primary);"></i>
            Manajemen Data Pelanggan & Riwayat Belanja
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('customerModal')">
            <i class="fas fa-user-plus"></i> Tambah Pelanggan
        </button>
    </div>

    <!-- Quick Search Box -->
    <div style="margin-bottom:18px;max-width:320px;">
        <input type="text" id="tableSearchInput" class="form-control" placeholder="Cari nama atau nomor WhatsApp...">
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Nama Pelanggan</th>
                    <th>No. WhatsApp / HP</th>
                    <th>Email</th>
                    <th style="text-align:center;">Total Transaksi</th>
                    <th style="text-align:right;">Total Belanja</th>
                    <th style="text-align:right;">Tunggakan Piutang</th>
                    <th>Catatan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:35px;color:var(--text-muted);">Belum ada data pelanggan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($c['name']) ?></strong>
                            </td>
                            <td>
                                <?php if (!empty($c['phone']) && $c['phone'] !== '-'): ?>
                                    <?php 
                                    $cleanPhone = preg_replace('/[^0-9]/', '', $c['phone']);
                                    if (substr($cleanPhone, 0, 1) === '0') $cleanPhone = '62' . substr($cleanPhone, 1);
                                    ?>
                                    <a href="https://wa.me/<?= $cleanPhone ?>" target="_blank" class="status-badge badge-success" style="font-size:0.75rem;">
                                        <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($c['phone']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:var(--text-muted);"><?= htmlspecialchars($c['email'] ?: '-') ?></td>
                            <td style="text-align:center;">
                                <span class="status-badge badge-secondary"><?= (int)$c['order_count'] ?> Pesanan</span>
                            </td>
                            <td style="text-align:right;" class="text-mono font-weight-bold color-primary">
                                <?= format_rupiah($c['total_spent']) ?>
                            </td>
                            <td style="text-align:right;" class="text-mono">
                                <?php if ((float)$c['piutang_amount'] > 0): ?>
                                    <span class="status-badge badge-danger"><?= format_rupiah($c['piutang_amount']) ?></span>
                                <?php else: ?>
                                    <span style="color:var(--success);font-size:0.8rem;"><i class="fas fa-check"></i> Lunas</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($c['notes'] ?: '-') ?></td>
                            <td>
                                <div style="display:flex;gap:4px;">
                                    <button type="button" class="btn btn-outline btn-sm" onclick='editCustomer(<?= json_encode($c) ?>)' title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="<?= BASE_URL ?>/index.php?page=topup&q=<?= urlencode($c['name']) ?>" class="btn btn-outline btn-sm" title="Lihat Riwayat Pesanan">
                                        <i class="fas fa-history"></i>
                                    </a>
                                    <?php if (has_role('admin')): ?>
                                        <a href="<?= BASE_URL ?>/index.php?page=pelanggan&delete=<?= $c['id'] ?>" class="btn btn-danger btn-sm" title="Hapus" onclick="return confirm('Hapus data pelanggan ini?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah/Edit Pelanggan -->
<div class="modal-overlay" id="customerModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title" id="custModalTitle"><i class="fas fa-user-plus me-2"></i> Tambah Pelanggan Baru</h3>
            <button type="button" class="btn-close" onclick="closeModal('customerModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="save_customer" value="1">
            <input type="hidden" name="cust_id" id="formCustId" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="custName">Nama Lengkap Pelanggan *</label>
                    <input type="text" name="name" id="custName" class="form-control" placeholder="Contoh: Rian Hidayat" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="custPhone">No. WhatsApp / HP</label>
                    <input type="text" name="phone" id="custPhone" class="form-control" placeholder="Contoh: 081234567890">
                </div>
                <div class="form-group">
                    <label class="form-label" for="custEmail">Email (Opsional)</label>
                    <input type="email" name="email" id="custEmail" class="form-control" placeholder="pelanggan@email.com">
                </div>
                <div class="form-group">
                    <label class="form-label" for="custNotes">Catatan Pelanggan</label>
                    <textarea name="notes" id="custNotes" class="form-control" rows="2" placeholder="Catatan preferensi game / ID akun pelanggan"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('customerModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Pelanggan</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCustomer(c) {
    document.getElementById('custModalTitle').innerHTML = '<i class="fas fa-user-edit me-2"></i> Edit Data Pelanggan';
    document.getElementById('formCustId').value = c.id;
    document.getElementById('custName').value = c.name;
    document.getElementById('custPhone').value = c.phone || '';
    document.getElementById('custEmail').value = c.email || '';
    document.getElementById('custNotes').value = c.notes || '';
    openModal('customerModal');
}
</script>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
