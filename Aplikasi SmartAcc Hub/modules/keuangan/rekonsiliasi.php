<?php
/**
 * SmartAcc Hub - Rekonsiliasi Kas & Bank
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();
$currentUser = current_user();

// Handle Adjustment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_adjustment'])) {
    $accountId = (int)$_POST['account_id'];
    $bookBalance = (float)$_POST['book_balance'];
    $realBalance = (float)$_POST['real_balance'];
    $difference = $realBalance - $bookBalance;
    $notes = trim($_POST['notes'] ?? 'Penyesuaian Selisih Kas / Rekonsiliasi');

    if ($accountId > 0 && abs($difference) > 0.01) {
        $accName = $db->query("SELECT name FROM accounts WHERE id = $accountId")->fetchColumn();
        $jno = generate_journal_no();
        $today = date('Y-m-d');

        // Akun Beban/Pendapatan Selisih Kas
        // If difference > 0 (Surplus): Debit Kas, Kredit Pendapatan Lain-lain (4301)
        // If difference < 0 (Shortage): Debit Beban Operasional Lainnya (6105), Kredit Kas
        if ($difference > 0) {
            $accOtherInc = $db->query("SELECT id FROM accounts WHERE code='4301'")->fetchColumn();
            $items = [
                ['account_id' => $accountId, 'debit' => abs($difference), 'credit' => 0, 'memo' => 'Penyesuaian Selisih Lebih Kas ' . $accName],
                ['account_id' => $accOtherInc, 'debit' => 0, 'credit' => abs($difference), 'memo' => 'Pendapatan Selisih Kas ' . $accName]
            ];
        } else {
            $accOtherExp = $db->query("SELECT id FROM accounts WHERE code='6105'")->fetchColumn();
            $items = [
                ['account_id' => $accOtherExp, 'debit' => abs($difference), 'credit' => 0, 'memo' => 'Beban Selisih Kurang Kas ' . $accName],
                ['account_id' => $accountId, 'debit' => 0, 'credit' => abs($difference), 'memo' => 'Penyesuaian Selisih Kurang Kas ' . $accName]
            ];
        }

        create_journal_entry($db, $jno, $today, 'PENYESUAIAN', 'REKON-' . date('Ymd'), "Rekonsiliasi & Penyesuaian Saldo {$accName}: {$notes}", $items, $currentUser['username']);
        log_activity('RECONCILIATION', "Rekonsiliasi {$accName}: penyesuaian selisih " . format_rupiah($difference));
        set_flash('success', "Penyesuaian selisih rekonsiliasi {$accName} sebesar " . format_rupiah($difference) . " berhasil dicatat ke jurnal penyesuaian.");
        header('Location: ' . BASE_URL . '/index.php?page=rekonsiliasi');
        exit;
    }
}

// Fetch Cash & Bank Accounts
$cashAccounts = $db->query("SELECT * FROM accounts WHERE is_cash_bank = 1 ORDER BY code ASC")->fetchAll();

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="card-box">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-check-double" style="color:var(--primary);"></i>
            Rekonsiliasi Saldo Kas & Bank
        </div>
        <span style="font-size:0.8rem;color:var(--text-muted);font-weight:600;">Per Tanggal: <?= format_date(date('Y-m-d')) ?></span>
    </div>

    <p style="color:var(--text-muted);font-size:0.88rem;margin-bottom:20px;">
        Cocokkan saldo catatan sistem akuntansi dengan fisik uang kas di kasir atau rekening koran bank riil Anda. Jika terjadi selisih, sistem dapat membuat jurnal penyesuaian otomatis.
    </p>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Akun Rekening</th>
                    <th>Saldo Menurut Sistem (Buku)</th>
                    <th>Aksi Rekonsiliasi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cashAccounts as $ca): ?>
                    <?php $bookBal = get_account_balance($db, (int)$ca['id']); ?>
                    <tr>
                        <td class="text-mono"><strong><?= htmlspecialchars($ca['code']) ?></strong></td>
                        <td>
                            <strong style="font-size:0.95rem;"><?= htmlspecialchars($ca['name']) ?></strong><br>
                            <small style="color:var(--text-muted);"><?= htmlspecialchars($ca['description']) ?></small>
                        </td>
                        <td class="text-mono" style="font-size:1.05rem;font-weight:800;color:var(--primary);">
                            <?= format_rupiah($bookBal) ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-outline btn-sm" onclick='openReconModal(<?= json_encode($ca) ?>, <?= $bookBal ?>)'>
                                <i class="fas fa-calculator"></i> Cocokkan Saldo Fisik / Koran
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Rekonsiliasi & Penyesuaian -->
<div class="modal-overlay" id="reconModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-balance-scale me-2"></i> Rekonsiliasi Saldo Akun</h3>
            <button type="button" class="btn-close" onclick="closeModal('reconModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="submit_adjustment" value="1">
            <input type="hidden" name="account_id" id="reconAccountId" value="">
            <input type="hidden" name="book_balance" id="reconBookBalanceInput" value="0">

            <div class="modal-body">
                <div style="background:var(--bg-card-subtle);padding:14px;border-radius:var(--radius-md);margin-bottom:18px;">
                    <div style="font-weight:700;font-size:1rem;" id="reconAccName">-</div>
                    <div style="display:flex;justify-content:space-between;margin-top:6px;">
                        <span style="font-size:0.85rem;color:var(--text-muted);">Saldo Menurut Sistem:</span>
                        <strong class="text-mono" id="reconBookBalanceDisplay" style="color:var(--primary);font-size:1.05rem;">Rp 0</strong>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="realBalance">Saldo Fisik / Rekening Koran Riil (Rp) *</label>
                    <input type="number" step="1" name="real_balance" id="realBalance" class="form-control" placeholder="Masukkan saldo riil..." required oninput="calcDifference()">
                    <div class="input-hint">Ketik saldo aktual yang tertera di rekening bank atau hitungan fisik brankas.</div>
                </div>

                <div style="background:var(--bg-card-subtle);padding:14px;border-radius:var(--radius-md);margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:0.85rem;color:var(--text-muted);">Selisih (Riil - Buku):</span>
                    <strong class="text-mono" id="reconDifferenceDisplay" style="font-size:1.15rem;">Rp 0</strong>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reconNotes">Keterangan / Alasan Selisih</label>
                    <input type="text" name="notes" id="reconNotes" class="form-control" placeholder="Contoh: Selisih pembulatan kasir toko / biaya administrasi belum tercatat">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('reconModal')">Tutup</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitRecon"><i class="fas fa-check"></i> Simpan Penyesuaian</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentBook = 0;

function openReconModal(acc, bookBal) {
    currentBook = bookBal;
    document.getElementById('reconAccountId').value = acc.id;
    document.getElementById('reconBookBalanceInput').value = bookBal;
    document.getElementById('reconAccName').textContent = acc.code + ' - ' + acc.name;
    document.getElementById('reconBookBalanceDisplay').textContent = 'Rp ' + bookBal.toLocaleString('id-ID');
    document.getElementById('realBalance').value = bookBal;
    calcDifference();
    openModal('reconModal');
}

function calcDifference() {
    const real = parseFloat(document.getElementById('realBalance').value || 0);
    const diff = real - currentBook;
    const diffEl = document.getElementById('reconDifferenceDisplay');
    const btnSubmit = document.getElementById('btnSubmitRecon');

    if (Math.abs(diff) < 0.01) {
        diffEl.textContent = 'Rp 0 (Cocok / Seimbang)';
        diffEl.style.color = 'var(--success)';
        btnSubmit.disabled = true;
    } else {
        diffEl.textContent = (diff > 0 ? '+Rp ' : '-Rp ') + Math.abs(diff).toLocaleString('id-ID') + (diff > 0 ? ' (Lebih)' : ' (Kurang)');
        diffEl.style.color = diff > 0 ? 'var(--info)' : 'var(--danger)';
        btnSubmit.disabled = false;
    }
}
</script>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
