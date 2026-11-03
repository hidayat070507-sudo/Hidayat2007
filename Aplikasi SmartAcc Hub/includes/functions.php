<?php
/**
 * SmartAcc Hub - Global Helper Functions
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/auth.php';

function format_rupiah($number, bool $prefix = true): string {
    $num = (float)$number;
    $formatted = number_format($num, 0, ',', '.');
    return $prefix ? 'Rp ' . $formatted : $formatted;
}

function format_date(?string $dateStr): string {
    if (empty($dateStr)) return '-';
    $time = strtotime($dateStr);
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    return date('d', $time) . ' ' . $months[(int)date('m', $time)] . ' ' . date('Y', $time);
}

function format_datetime(?string $dateStr): string {
    if (empty($dateStr)) return '-';
    $time = strtotime($dateStr);
    return date('d/m/Y H:i', $time);
}

function set_flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash_message'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

function display_flash(): string {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        $icon = 'info-circle';
        if ($msg['type'] === 'success') $icon = 'check-circle';
        if ($msg['type'] === 'danger') $icon = 'exclamation-circle';
        if ($msg['type'] === 'warning') $icon = 'exclamation-triangle';

        return '<div class="alert alert-' . htmlspecialchars($msg['type']) . ' alert-dismissible fade show" role="alert">
            <i class="fas fa-' . $icon . ' me-2"></i> ' . htmlspecialchars($msg['message']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
    return '';
}

function log_activity(string $action, string $description): void {
    try {
        $db = Database::getConnection();
        $user = current_user();
        $userId = $user ? $user['id'] : null;
        $userName = $user ? $user['full_name'] : 'Guest/System';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, user_name, action, description, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $userName, $action, $description, $ip]);
    } catch (Exception $e) {
        // Silently fail if log table error
    }
}

function get_setting(string $key, string $default = ''): string {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT value_data FROM settings WHERE key_name = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function update_setting(string $key, string $value): void {
    $db = Database::getConnection();
    $stmt = $db->prepare("INSERT INTO settings (key_name, value_data) VALUES (?, ?) 
        ON CONFLICT(key_name) DO UPDATE SET value_data = excluded.value_data");
    $stmt->execute([$key, $value]);
}

function render_status_badge(string $status): string {
    $statusUpper = strtoupper($status);
    $map = [
        'BERHASIL' => ['class' => 'badge-success', 'icon' => 'check-circle', 'text' => 'Berhasil'],
        'SELESAI' => ['class' => 'badge-success', 'icon' => 'check-circle', 'text' => 'Selesai'],
        'LUNAS' => ['class' => 'badge-success', 'icon' => 'check-circle', 'text' => 'Lunas'],
        'DIPROSES' => ['class' => 'badge-info', 'icon' => 'sync-alt fa-spin', 'text' => 'Diproses'],
        'PENDING' => ['class' => 'badge-warning', 'icon' => 'clock', 'text' => 'Pending'],
        'BELUM_LUNAS' => ['class' => 'badge-warning', 'icon' => 'clock', 'text' => 'Belum Lunas'],
        'SEBAGIAN' => ['class' => 'badge-warning', 'icon' => 'hourglass-half', 'text' => 'Sebagian'],
        'GAGAL' => ['class' => 'badge-danger', 'icon' => 'times-circle', 'text' => 'Gagal'],
        'ACTIVE' => ['class' => 'badge-success', 'icon' => 'check', 'text' => 'Aktif'],
        'INACTIVE' => ['class' => 'badge-secondary', 'icon' => 'ban', 'text' => 'Nonaktif']
    ];

    $cfg = $map[$statusUpper] ?? ['class' => 'badge-secondary', 'icon' => 'circle', 'text' => $status];
    return '<span class="status-badge ' . $cfg['class'] . '"><i class="fas fa-' . $cfg['icon'] . '"></i> ' . $cfg['text'] . '</span>';
}

function generate_invoice_no(): string {
    $db = Database::getConnection();
    $prefix = 'INV-' . date('ymd') . '-';
    $stmt = $db->prepare("SELECT invoice_no FROM topup_orders WHERE invoice_no LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    if ($last) {
        $num = (int)substr($last, strlen($prefix)) + 1;
    } else {
        $num = 1;
    }
    return $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
}

function generate_journal_no(): string {
    $db = Database::getConnection();
    $prefix = 'JU-' . date('Ymd') . '-';
    $stmt = $db->prepare("SELECT journal_no FROM journal_entries WHERE journal_no LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    if ($last) {
        $num = (int)substr($last, strlen($prefix)) + 1;
    } else {
        $num = 1;
    }
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function generate_trans_no(string $type = 'BKM'): string {
    $db = Database::getConnection();
    $prefix = $type . '-' . date('Ymd') . '-';
    $stmt = $db->prepare("SELECT trans_no FROM cash_transactions WHERE trans_no LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    if ($last) {
        $num = (int)substr($last, strlen($prefix)) + 1;
    } else {
        $num = 1;
    }
    return $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
}

// Accounting Helpers
function create_journal_entry(PDO $db, string $journalNo, string $date, string $refType, ?string $refId, string $description, array $items, string $createdBy): int {
    $stmt = $db->prepare("INSERT INTO journal_entries (journal_no, entry_date, reference_type, reference_id, description, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$journalNo, $date, $refType, $refId, $description, $createdBy]);
    $journalId = (int)$db->lastInsertId();

    $stmtItem = $db->prepare("INSERT INTO journal_items (journal_id, account_id, debit, credit, memo) VALUES (?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $stmtItem->execute([
            $journalId,
            $item['account_id'],
            (float)($item['debit'] ?? 0),
            (float)($item['credit'] ?? 0),
            $item['memo'] ?? ''
        ]);
    }

    return $journalId;
}

function get_account_balance(PDO $db, int $accountId, ?string $upToDate = null): float {
    // Get account info
    $stmtAcc = $db->prepare("SELECT normal_balance FROM accounts WHERE id = ?");
    $stmtAcc->execute([$accountId]);
    $acc = $stmtAcc->fetch();
    if (!$acc) return 0.0;

    $sql = "SELECT SUM(ji.debit) as total_debit, SUM(ji.credit) as total_credit 
            FROM journal_items ji 
            JOIN journal_entries je ON ji.journal_id = je.id 
            WHERE ji.account_id = ?";
    $params = [$accountId];
    if ($upToDate) {
        $sql .= " AND je.entry_date <= ?";
        $params[] = $upToDate;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $res = $stmt->fetch();

    $debit = (float)($res['total_debit'] ?? 0);
    $credit = (float)($res['total_credit'] ?? 0);

    if ($acc['normal_balance'] === 'DEBIT') {
        return $debit - $credit;
    } else {
        return $credit - $debit;
    }
}

function get_cash_and_bank_balances(PDO $db): array {
    $stmt = $db->query("SELECT id, code, name, normal_balance FROM accounts WHERE is_cash_bank = 1 ORDER BY code ASC");
    $accounts = $stmt->fetchAll();

    $result = [];
    $total = 0.0;
    foreach ($accounts as $a) {
        $bal = get_account_balance($db, (int)$a['id']);
        $result[] = [
            'id' => $a['id'],
            'code' => $a['code'],
            'name' => $a['name'],
            'balance' => $bal
        ];
        $total += $bal;
    }
    return ['accounts' => $result, 'total' => $total];
}
