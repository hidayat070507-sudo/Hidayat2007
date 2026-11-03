<?php
/**
 * SmartAcc Hub - Manajemen Pengguna & Hak Akses (RBAC)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

require_role('admin');

$db = Database::getConnection();
$currentUser = current_user();

// Handle Add / Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $username = trim($_POST['username']);
    $fullName = trim($_POST['full_name']);
    $role = $_POST['role'] ?? 'kasir';
    $email = trim($_POST['email'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($fullName)) {
        if (!empty($_POST['user_id'])) {
            $userId = (int)$_POST['user_id'];
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET username = ?, full_name = ?, role = ?, email = ?, status = ?, password = ? WHERE id = ?");
                $stmt->execute([$username, $fullName, $role, $email, $status, $hash, $userId]);
            } else {
                $stmt = $db->prepare("UPDATE users SET username = ?, full_name = ?, role = ?, email = ?, status = ? WHERE id = ?");
                $stmt->execute([$username, $fullName, $role, $email, $status, $userId]);
            }
            log_activity('UPDATE_USER', "Memperbarui data pengguna {$username} ({$role})");
            set_flash('success', "Data pengguna {$username} berhasil diperbarui.");
        } else {
            // Check username uniqueness
            $chk = $db->prepare("SELECT id FROM users WHERE username = ?");
            $chk->execute([$username]);
            if ($chk->fetch()) {
                set_flash('danger', "Username {$username} sudah digunakan!");
            } else {
                $pw = !empty($password) ? $password : '123456';
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password, full_name, role, email, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hash, $fullName, $role, $email, $status]);
                log_activity('ADD_USER', "Menambahkan pengguna baru {$username} ({$role})");
                set_flash('success', "Pengguna baru {$username} berhasil ditambahkan.");
            }
        }
    }
    header('Location: ' . BASE_URL . '/index.php?page=users');
    exit;
}

$users = $db->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="card-box">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-user-shield" style="color:var(--primary);"></i>
            Pengguna & Hak Akses (Admin, Kasir, Bagian Keuangan)
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('userModal')">
            <i class="fas fa-plus"></i> Tambah Pengguna Baru
        </button>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Nama Lengkap</th>
                    <th>Hak Akses (Role)</th>
                    <th>Email</th>
                    <th>Status Akun</th>
                    <th>Tanggal Dibuat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="text-mono"><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="status-badge badge-primary"><i class="fas fa-crown"></i> Admin (Semua Akses)</span>
                            <?php elseif ($u['role'] === 'keuangan'): ?>
                                <span class="status-badge badge-info"><i class="fas fa-calculator"></i> Bagian Keuangan</span>
                            <?php else: ?>
                                <span class="status-badge badge-secondary"><i class="fas fa-cash-register"></i> Kasir Top Up</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--text-muted);"><?= htmlspecialchars($u['email'] ?: '-') ?></td>
                        <td><?= render_status_badge($u['status']) ?></td>
                        <td style="font-size:0.8rem;color:var(--text-muted);"><?= format_date($u['created_at']) ?></td>
                        <td>
                            <button type="button" class="btn btn-outline btn-sm" onclick='editUser(<?= json_encode($u) ?>)' title="Edit Pengguna">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah/Edit User -->
<div class="modal-overlay" id="userModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title" id="userModalTitle"><i class="fas fa-user-plus me-2"></i> Tambah Pengguna Baru</h3>
            <button type="button" class="btn-close" onclick="closeModal('userModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="save_user" value="1">
            <input type="hidden" name="user_id" id="formUserId" value="">
            <div class="modal-body">
                <div style="display:grid;grid-template-columns: 1fr 1fr;gap:14px;">
                    <div class="form-group">
                        <label class="form-label" for="formUsername">Username *</label>
                        <input type="text" name="username" id="formUsername" class="form-control" placeholder="Contoh: kasir2" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="formPassword">Password</label>
                        <input type="password" name="password" id="formPassword" class="form-control" placeholder="••••••••">
                        <div class="input-hint" id="pwHint">Kosongkan jika tidak ingin mengganti password.</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="formFullName">Nama Lengkap *</label>
                    <input type="text" name="full_name" id="formFullName" class="form-control" placeholder="Contoh: Ahmad Kasir" required>
                </div>

                <div style="display:grid;grid-template-columns: 1fr 1fr;gap:14px;">
                    <div class="form-group">
                        <label class="form-label" for="formRole">Hak Akses (Role) *</label>
                        <select name="role" id="formRole" class="form-select" required>
                            <option value="kasir">Kasir (Topup & Kas)</option>
                            <option value="keuangan">Bagian Keuangan & Akuntansi</option>
                            <option value="admin">Administrator (Akses Penuh)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="formStatus">Status Akun</label>
                        <select name="status" id="formStatus" class="form-select">
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="formEmail">Email</label>
                    <input type="email" name="email" id="formEmail" class="form-control" placeholder="staf@smartacc.store">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('userModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Pengguna</button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(u) {
    document.getElementById('userModalTitle').innerHTML = '<i class="fas fa-user-edit me-2"></i> Edit Data Pengguna';
    document.getElementById('formUserId').value = u.id;
    document.getElementById('formUsername').value = u.username;
    document.getElementById('formFullName').value = u.full_name;
    document.getElementById('formRole').value = u.role;
    document.getElementById('formStatus').value = u.status;
    document.getElementById('formEmail').value = u.email || '';
    document.getElementById('pwHint').textContent = 'Kosongkan jika tidak ingin mengubah password saat ini.';
    openModal('userModal');
}
</script>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
