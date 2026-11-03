<?php
/**
 * SmartAcc Hub - Catatan Utang & Piutang (Debts & Receivables)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();
$currentUser = current_user();

// Handle New Debt/Receivable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_debt'])) {
    $type = $_POST['type']; // UTANG or PIUTANG
    $contactType = $_POST['contact_type'];
    $contactName = trim($_POST['contact_name']);
    $amount = (float)$_POST['amount'];
    $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $notes = trim($_POST['notes'] ?? '');

    if (!empty($contactName) && $amount > 0) {
        $prefix = ($type === 'UTANG') ? 'UTG-' : 'PIU-';
        $debtNo = $prefix . date('Ymd') . '-' . rand(100, 999);

        $stmt = $db->prepare("INSERT INTO debts (debt_no, type, contact_type, contact_name, amount, due_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$debtNo, $type, $contactType, $contactName, $amount, $dueDate, $notes]);

        log_activity('ADD_DEBT', "Mencatat {$type} {$debtNo} untuk {$contactName} senilai " . format_rupiah($amount));
        set_flash('success', "Catatan {$type} {$debtNo} berhasil disimpan.");
        header('Location: ' . BASE_URL . '/index.php?page=utang_piutang');
        exit;
    }
}

// Handle Payment / Settlement of Debt/Receivable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_debt'])) {
    $debtId = (int)$_POST['debt_id'];
    $payAmount = (float)$_POST['pay_amount'];
    $accountId = (int)$_POST['account_id']; // Kas/Bank
    $payDate = $_POST['pay_date'] ?: date('Y-m-d');

    $stmtD = $db->prepare("SELECT * FROM debts WHERE id = ?");
    $stmtD->execute([$debtId]);
    $debt = $stmtD->fetch();

    if ($debt && $payAmount > 0 && $accountId > 0) {
        $newPaid = (float)$debt['paid_amount'] + $payAmount;
        $status = ($newPaid >= (float)$debt['amount'] - 0.01) ? 'LUNAS' : 'SEBAGIAN';

        $stmtUp = $db->prepare("UPDATE debts SET paid_amount = ?, status = ? WHERE id = ?");
        $stmtUp->execute([$newPaid, $status, $debtId]);

        // Accounting Journal
        $jno = generate_journal_no();
        if ($debt['type'] === 'PIUTANG') {
            // Pelunasan Piutang: Kas bertambah (Debit Kas), Piutang berkurang (Kredit Piutang 1105)
            $accPiutang = $db->query("SELECT id FROM accounts WHERE code='1105'")->fetchColumn();
            $items = [
                ['account_id' => $accountId, 'debit' => $payAmount, 'credit' => 0, 'memo' => 'Penerimaan pelunasan piutang ' . $debt['contact_name']],
                ['account_id' => $accPiutang, 'debit' => 0, 'credit' => $payAmount, 'memo' => 'Pengurangan piutang ' . $debt['debt_no']]
            ];
            create_journal_entry($db, $jno, $payDate, 'KAS_MASUK', $debt['debt_no'], "Pelunasan Piutang {$debt['contact_name']} ({$debt['debt_no']})", $items, $currentUser['username']);
        } else {
            // Pembayaran Utang: Utang berkurang (Debit Utang 2101), Kas berkurang (Kredit Kas)
            $accUtang = $db->query("SELECT id FROM accounts WHERE code='2101'")->fetchColumn();
            $items = [
                ['account_id' => $accUtang, 'debit' => $payAmount, 'credit' => 0, 'memo' => 'Pembayaran utang ' . $debt['debt_no']],
                ['account_id' => $accountId, 'debit' => 0, 'credit' => $payAmount, 'memo' => 'Pengeluaran kas bayar utang ' . $debt['contact_name']]
            ];
            create_journal_entry($db, $jno, $payDate, 'KAS_KELUAR', $debt['debt_no'], "Pembayaran Utang {$debt['contact_name']} ({$debt['debt_no']})", $items, $currentUser['username']);
        }

        log_activity('PAY_DEBT', "Mencatat pembayaran {$debt['type']} {$debt['debt_no']} sebesar " . format_rupiah($payAmount));
        set_flash('success', "Pembayaran {$debt['type']} sebesar " . format_rupiah($payAmount) . " berhasil dicatat ke sistem dan jurnal!");
        header('Location: ' . BASE_URL . '/index.php?page=utang_piutang');
        exit;
    }
}

// Filter query
$typeFilter = $_GET['type'] ?? '';
$sql = "SELECT * FROM debts WHERE 1=1";
$params = [];
if (!empty($typeFilter)) {
    $sql .= " AND type = ?";
    $params[] = $typeFilter;
}
$sql .= " ORDER BY status ASC, id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$debts = $stmt->fetchAll();

// Totals
$totalPiutangBelumLunas = (float)$db->query("SELECT COALESCE(SUM(amount - paid_amount), 0) FROM debts WHERE type = 'PIUTANG' AND status != 'LUNAS'")->fetchColumn();
$totalUtangBelumLunas = (float)$db->query("SELECT COALESCE(SUM(amount - paid_amount), 0) FROM debts WHERE type = 'UTANG' AND status != 'LUNAS'")->fetchColumn();

$cashAccounts = $db->query("SELECT id, code, name FROM accounts WHERE is_cash_bank = 1 ORDER BY code ASC")->fetchAll();

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<!-- Summary Cards -->
<div class="stat-grid">
    <div class="stat-card emerald">
        <div class="stat-info">
            <div class="stat-label">Total Piutang Belum Lunas (Hak Uang Anda)</div>
            <div class="stat-value"><?= format_rupiah($totalPiutangBelumLunas) ?></div>
            <div class="stat-desc">Tagihan belum tertagih dari pelanggan</div>
        </div>
        <div class="stat-icon emerald"><i class="fas fa-hand-holding-usd"></i></div>
    </div>

    <div class="stat-card rose">
        <div class="stat-info">
            <div class="stat-label">Total Utang Belum Lunas (Kewajiban)</div>
            <div class="stat-value"><?= format_rupiah($totalUtangBelumLunas) ?></div>
            <div class="stat-desc">Tagihan harus dibayar ke supplier / pihak lain</div>
        </div>
        <div class="stat-icon rose"><i class="fas fa-file-invoice"></i></div>
    </div>
</div>

<div class="card-box">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-hand-holding-usd" style="color:var(--primary);"></i>
            Catatan Utang & Piutang Usaha
        </div>
        <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-primary btn-sm" onclick="openNewDebtModal('PIUTANG')">
                <i class="fas fa-plus"></i> Catat Piutang Pelanggan
            </button>
            <button type="button" class="btn btn-danger btn-sm" onclick="openNewDebtModal('UTANG')">
                <i class="fas fa-plus"></i> Catat Utang Usaha
            </button>
        </div>
    </div>

    <!-- Filter Buttons -->
    <div style="display:flex;gap:8px;margin-bottom:18px;">
        <a href="<?= BASE_URL ?>/index.php?page=utang_piutang" class="btn btn-sm <?= empty($typeFilter) ? 'btn-primary' : 'btn-outline' ?>">Semua</a>
        <a href="<?= BASE_URL ?>/index.php?page=utang_piutang&type=PIUTANG" class="btn btn-sm <?= $typeFilter === 'PIUTANG' ? 'btn-primary' : 'btn-outline' ?>">Piutang (Tagihan Masuk)</a>
        <a href="<?= BASE_URL ?>/index.php?page=utang_piutang&type=UTANG" class="btn btn-sm <?= $typeFilter === 'UTANG' ? 'btn-primary' : 'btn-outline' ?>">Utang (Kewajiban Keluar)</a>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>No Referensi</th>
                    <th>Jenis</th>
                    <th>Nama Kontak / Pihak</th>
                    <th>Total Tagihan</th>
                    <th>Sudah Dibayar</th>
                    <th>Sisa Tagihan</th>
                    <th>Jatuh Tempo</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($debts)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;padding:35px;color:var(--text-muted);">Tidak ada catatan utang atau piutang.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($debts as $d): ?>
                        <?php $sisa = (float)$d['amount'] - (float)$d['paid_amount']; ?>
                        <tr>
                            <td><strong class="text-mono"><?= htmlspecialchars($d['debt_no']) ?></strong></td>
                            <td>
                                <?php if ($d['type'] === 'PIUTANG'): ?>
                                    <span class="status-badge badge-success"><i class="fas fa-arrow-down"></i> Piutang</span>
                                <?php else: ?>
                                    <span class="status-badge badge-danger"><i class="fas fa-arrow-up"></i> Utang</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($d['contact_name']) ?></strong><br>
                                <small style="color:var(--text-muted);"><?= htmlspecialchars($d['notes'] ?: '-') ?></small>
                            </td>
                            <td class="text-mono font-weight-bold"><?= format_rupiah($d['amount']) ?></td>
                            <td class="text-mono" style="color:var(--success);"><?= format_rupiah($d['paid_amount']) ?></td>
                            <td class="text-mono font-weight-bold" style="color:<?= $sisa > 0 ? 'var(--danger)' : 'var(--text-muted)' ?>;">
                                <?= format_rupiah($sisa) ?>
                            </td>
                            <td style="font-size:0.8rem;"><?= $d['due_date'] ? format_date($d['due_date']) : '-' ?></td>
                            <td><?= render_status_badge($d['status']) ?></td>
                            <td>
                                <?php if ($d['status'] !== 'LUNAS'): ?>
                                    <button type="button" class="btn btn-primary btn-sm" onclick='openPayModal(<?= json_encode($d) ?>, <?= $sisa ?>)'>
                                        <i class="fas fa-money-bill-wave"></i> Bayar
                                    </button>
                                <?php else: ?>
                                    <span style="font-size:0.75rem;color:var(--success);font-weight:700;"><i class="fas fa-check-circle"></i> Selesai</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Utang/Piutang -->
<div class="modal-overlay" id="debtModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title" id="debtModalTitle"><i class="fas fa-edit me-2"></i> Tambah Tagihan</h3>
            <button type="button" class="btn-close" onclick="closeModal('debtModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="save_debt" value="1">
            <input type="hidden" name="type" id="formDebtType" value="PIUTANG">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="contactType">Kategori Pihak</label>
                    <select name="contact_type" id="contactType" class="form-select">
                        <option value="CUSTOMER">Pelanggan</option>
                        <option value="SUPPLIER">Supplier</option>
                        <option value="LAINNYA">Pihak Lainnya</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="contactName">Nama Orang / Perusahaan *</label>
                    <input type="text" name="contact_name" id="contactName" class="form-control" placeholder="Contoh: Rian Hidayat atau Supplier API" required>
                </div>

                <div style="display:grid;grid-template-columns: 1fr 1fr;gap:14px;">
                    <div class="form-group">
                        <label class="form-label" for="debtAmount">Nominal Tagihan (Rp) *</label>
                        <input type="number" step="100" name="amount" id="debtAmount" class="form-control" placeholder="50000" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="debtDueDate">Tanggal Jatuh Tempo</label>
                        <input type="date" name="due_date" id="debtDueDate" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="debtNotes">Keterangan / Alasan Tagihan</label>
                    <textarea name="notes" id="debtNotes" class="form-control" rows="2" placeholder="Contoh: Pembelian 355 Diamonds belum bayar"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('debtModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Catatan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Bayar / Pelunasan -->
<div class="modal-overlay" id="payModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-hand-holding-usd me-2"></i> Form Pembayaran / Pelunasan</h3>
            <button type="button" class="btn-close" onclick="closeModal('payModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="pay_debt" value="1">
            <input type="hidden" name="debt_id" id="payDebtId" value="">

            <div class="modal-body">
                <div style="background:var(--bg-card-subtle);padding:14px;border-radius:var(--radius-md);margin-bottom:18px;">
                    <div style="font-weight:700;font-size:1rem;" id="payContactName">-</div>
                    <div style="display:flex;justify-content:space-between;margin-top:6px;">
                        <span style="font-size:0.85rem;color:var(--text-muted);">Sisa Tagihan Belum Dibayar:</span>
                        <strong class="text-mono" id="paySisaDisplay" style="color:var(--danger);font-size:1.1rem;">Rp 0</strong>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="payAccountId">Rekening Kas / Bank Penerima atau Pembayar *</label>
                    <select name="account_id" id="payAccountId" class="form-select" required>
                        <?php foreach ($cashAccounts as $ca): ?>
                            <option value="<?= $ca['id'] ?>"><?= htmlspecialchars($ca['code']) ?> - <?= htmlspecialchars($ca['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:grid;grid-template-columns: 1fr 1fr;gap:14px;">
                    <div class="form-group">
                        <label class="form-label" for="payAmount">Nominal yang Dibayar (Rp) *</label>
                        <input type="number" step="100" name="pay_amount" id="payAmount" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="payDate">Tanggal Bayar</label>
                        <input type="date" name="pay_date" id="payDate" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('payModal')">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Proses Pembayaran</button>
            </div>
        </form>
    </div>
</div>

<script>
function openNewDebtModal(type) {
    document.getElementById('formDebtType').value = type;
    if (type === 'PIUTANG') {
        document.getElementById('debtModalTitle').innerHTML = '<i class="fas fa-arrow-down text-success me-2"></i> Catat Piutang Baru (Tagihan ke Pelanggan)';
        document.getElementById('contactType').value = 'CUSTOMER';
    } else {
        document.getElementById('debtModalTitle').innerHTML = '<i class="fas fa-arrow-up text-danger me-2"></i> Catat Utang Usaha Baru';
        document.getElementById('contactType').value = 'SUPPLIER';
    }
    openModal('debtModal');
}

function openPayModal(d, sisa) {
    document.getElementById('payDebtId').value = d.id;
    document.getElementById('payContactName').textContent = (d.type === 'PIUTANG' ? 'Tagihan Piutang: ' : 'Kewajiban Utang: ') + d.contact_name + ' (' + d.debt_no + ')';
    document.getElementById('paySisaDisplay').textContent = 'Rp ' + sisa.toLocaleString('id-ID');
    document.getElementById('payAmount').value = sisa;
    openModal('payModal');
}
</script>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
