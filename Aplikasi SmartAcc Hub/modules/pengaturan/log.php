<?php
/**
 * SmartAcc Hub - Riwayat Log Aktivitas Pengguna
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

require_role('admin');

$db = Database::getConnection();

// Fetch Logs
$logs = $db->query("SELECT * FROM activity_logs ORDER BY id DESC LIMIT 150")->fetchAll();

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="card-box">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-history" style="color:var(--primary);"></i>
            Riwayat Aktivitas & Audit Log Sistem
        </div>
        <span style="font-size:0.8rem;color:var(--text-muted);font-weight:600;">150 Aktivitas Terkini</span>
    </div>

    <!-- Quick Search Filter -->
    <div style="margin-bottom:18px;max-width:320px;">
        <input type="text" id="tableSearchInput" class="form-control" placeholder="Cari aksi atau nama pengguna...">
    </div>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th style="width:170px;">Waktu & Tanggal</th>
                    <th style="width:180px;">Nama Pengguna</th>
                    <th style="width:170px;">Tindakan / Aksi</th>
                    <th>Rincian Keterangan</th>
                    <th style="width:120px;">IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center;padding:35px;color:var(--text-muted);">Belum ada riwayat aktivitas.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td style="font-size:0.82rem;color:var(--text-muted);"><?= format_datetime($l['created_at']) ?></td>
                            <td><strong><?= htmlspecialchars($l['user_name']) ?></strong></td>
                            <td>
                                <span class="status-badge badge-secondary text-mono" style="font-size:0.7rem;">
                                    <?= htmlspecialchars($l['action']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($l['description']) ?></td>
                            <td class="text-mono" style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($l['ip_address'] ?: '127.0.0.1') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
