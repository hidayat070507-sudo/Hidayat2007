<?php
/**
 * SmartAcc Hub - Pengaturan Profil Usaha
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

require_role('admin');

$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $companyName = trim($_POST['company_name'] ?? '');
    $tagline = trim($_POST['company_tagline'] ?? '');
    $address = trim($_POST['company_address'] ?? '');
    $phone = trim($_POST['company_phone'] ?? '');
    $email = trim($_POST['company_email'] ?? '');
    $currency = trim($_POST['currency'] ?? 'Rp');

    update_setting('company_name', $companyName);
    update_setting('company_tagline', $tagline);
    update_setting('company_address', $address);
    update_setting('company_phone', $phone);
    update_setting('company_email', $email);
    update_setting('currency', $currency);

    log_activity('UPDATE_PROFILE', "Memperbarui profil toko usaha: {$companyName}");
    set_flash('success', "Profil usaha berhasil diperbarui.");
    header('Location: ' . BASE_URL . '/index.php?page=profil');
    exit;
}

$cName = get_setting('company_name', 'SmartAcc Game Store');
$cTagline = get_setting('company_tagline', 'Pusat Top Up Game Terpercaya & Akuntansi');
$cAddress = get_setting('company_address', 'Jl. Boulevard Digital No. 88, Jakarta');
$cPhone = get_setting('company_phone', '0812-3456-7890');
$cEmail = get_setting('company_email', 'support@smartacc.store');
$cCurrency = get_setting('currency', 'Rp');

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="card-box" style="max-width:700px;margin:0 auto 24px;">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-store" style="color:var(--primary);"></i>
            Pengaturan Profil Usaha & Identitas Toko
        </div>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="save_profile" value="1">

        <div class="form-group">
            <label class="form-label" for="companyName">Nama Usaha / Toko Top Up *</label>
            <input type="text" name="company_name" id="companyName" class="form-control" value="<?= htmlspecialchars($cName) ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="companyTagline">Slogan / Tagline Usaha</label>
            <input type="text" name="company_tagline" id="companyTagline" class="form-control" value="<?= htmlspecialchars($cTagline) ?>">
        </div>

        <div style="display:grid;grid-template-columns: 1fr 1fr;gap:16px;">
            <div class="form-group">
                <label class="form-label" for="companyPhone">No. WhatsApp / Telepon Toko</label>
                <input type="text" name="company_phone" id="companyPhone" class="form-control" value="<?= htmlspecialchars($cPhone) ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="companyEmail">Email Bisnis</label>
                <input type="email" name="company_email" id="companyEmail" class="form-control" value="<?= htmlspecialchars($cEmail) ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="companyAddress">Alamat Lengkap Usaha</label>
            <textarea name="company_address" id="companyAddress" class="form-control" rows="2"><?= htmlspecialchars($cAddress) ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label" for="currency">Simbol Mata Uang</label>
            <input type="text" name="currency" id="currency" class="form-control" value="<?= htmlspecialchars($cCurrency) ?>" style="max-width:140px;">
        </div>

        <div style="margin-top:24px;border-top:1px solid var(--border-color);padding-top:18px;display:flex;justify-content:flex-end;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i> Simpan Perubahan Profil
            </button>
        </div>
    </form>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
