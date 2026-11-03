<?php
/**
 * SmartAcc Hub - Backup & Pemulihan Database
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

require_role('admin');

$db = Database::getConnection();
$dbFile = SQLITE_FILE;

// Handle Download SQLite Backup
if (isset($_GET['action']) && $_GET['action'] === 'download_sqlite') {
    if (file_exists($dbFile)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/x-sqlite3');
        header('Content-Disposition: attachment; filename="backup_smartacc_' . date('Ymd_His') . '.sqlite"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($dbFile));
        readfile($dbFile);
        exit;
    }
}

// Handle Download MySQL SQL Dump
if (isset($_GET['action']) && $_GET['action'] === 'download_sql') {
    $sqlFile = DATABASE_PATH . '/database.sql';
    if (file_exists($sqlFile)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="schema_smartacc_mysql_' . date('Ymd_His') . '.sql"');
        header('Content-Length: ' . filesize($sqlFile));
        readfile($sqlFile);
        exit;
    }
}

// Handle Restore SQLite Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_database'])) {
    if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['backup_file']['tmp_name'];
        $fileName = $_FILES['backup_file']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($ext, ['sqlite', 'db', 'sqlite3'])) {
            // Backup current db first
            copy($dbFile, DATABASE_PATH . '/smartacc_pre_restore_' . date('Ymd_His') . '.bak');
            
            // Overwrite with uploaded file
            if (move_uploaded_file($fileTmp, $dbFile)) {
                log_activity('RESTORE_DATABASE', "Pemulihan database dari file {$fileName}");
                set_flash('success', "Database berhasil dipulihkan dari file backup {$fileName}!");
            } else {
                set_flash('danger', "Gagal memindahkan file database.");
            }
        } else {
            set_flash('danger', "Format file tidak valid. Harap unggah file berekstensi .sqlite atau .db");
        }
    } else {
        set_flash('danger', "Gagal mengunggah file backup.");
    }
    header('Location: ' . BASE_URL . '/index.php?page=backup');
    exit;
}

$fileSize = file_exists($dbFile) ? round(filesize($dbFile) / 1024, 2) . ' KB' : '0 KB';
$lastModified = file_exists($dbFile) ? date('d/m/Y H:i', filemtime($dbFile)) : '-';

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div style="max-width:850px;margin:0 auto 24px;">
    <!-- Backup Card -->
    <div class="card-box">
        <div class="card-box-header">
            <div class="card-box-title">
                <i class="fas fa-download" style="color:var(--primary);"></i>
                Cadangkan Database (Backup)
            </div>
        </div>

        <p style="color:var(--text-muted);font-size:0.88rem;margin-bottom:18px;">
            Simpan salinan data transaksi, jurnal, akun, dan pesanan pelanggan secara berkala untuk menjaga keamanan data usaha Anda.
        </p>

        <div style="background:var(--bg-card-subtle);padding:18px;border-radius:var(--radius-md);margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-weight:700;font-size:0.95rem;">Database Utama (SQLite)</div>
                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">
                    Ukuran Berkas: <strong><?= $fileSize ?></strong> &bull; Terakhir Diperbarui: <strong><?= $lastModified ?></strong>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/index.php?page=backup&action=download_sqlite" class="btn btn-primary btn-sm">
                <i class="fas fa-download"></i> Unduh File Database (.sqlite)
            </a>
        </div>

        <div style="background:var(--bg-card-subtle);padding:18px;border-radius:var(--radius-md);display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-weight:700;font-size:0.95rem;">Skema & Struktur MySQL (.sql)</div>
                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">
                    Untuk keperluan impor ke phpMyAdmin atau server MySQL hosting.
                </div>
            </div>
            <a href="<?= BASE_URL ?>/index.php?page=backup&action=download_sql" class="btn btn-outline btn-sm">
                <i class="fas fa-file-code"></i> Unduh MySQL Schema (.sql)
            </a>
        </div>
    </div>

    <!-- Restore Card -->
    <div class="card-box">
        <div class="card-box-header">
            <div class="card-box-title">
                <i class="fas fa-upload" style="color:var(--warning);"></i>
                Pemulihan Database (Restore)
            </div>
        </div>

        <div class="alert alert-warning" style="font-size:0.85rem;">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Perhatian:</strong> Memulihkan database akan menggantikan data yang ada saat ini dengan data dari file cadangan yang Anda unggah. Sistem akan membuat berkas cadangan otomatis sebelum proses dilakukan.
        </div>

        <form method="POST" action="" enctype="multipart/form-data" onsubmit="return confirm('Apakah Anda yakin ingin memulihkan database dari file ini? Data saat ini akan digantikan.')">
            <input type="hidden" name="restore_database" value="1">
            <div class="form-group">
                <label class="form-label" for="backupFile">Pilih Berkas Cadangan (.sqlite / .db)</label>
                <input type="file" name="backup_file" id="backupFile" class="form-control" accept=".sqlite,.db,.sqlite3" required>
            </div>

            <button type="submit" class="btn btn-danger btn-sm">
                <i class="fas fa-sync-alt"></i> Pulihkan Database Sekarang
            </button>
        </form>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
