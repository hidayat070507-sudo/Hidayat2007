<?php
/**
 * SmartAcc Hub - Export Handler (Excel/CSV and Printable Format)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

require_login();

$db = Database::getConnection();
$type = $_GET['type'] ?? 'topup';
$startDate = $_GET['start'] ?? date('Y-01-01');
$endDate = $_GET['end'] ?? date('Y-m-d');
$date = $_GET['date'] ?? date('Y-m-d');

$filename = "Export_{$type}_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
// UTF-8 BOM for Microsoft Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

if ($type === 'topup' || $type === 'transaksi') {
    fputcsv($output, ['No Invoice', 'Tanggal', 'Pelanggan', 'Game', 'Item', 'User ID Game', 'Server ID', 'Harga Modal', 'Harga Jual', 'Keuntungan', 'Metode Pembayaran', 'Status Pesanan']);
    $stmt = $db->prepare("SELECT * FROM topup_orders WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY id DESC");
    $stmt->execute([$startDate, $endDate]);
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['invoice_no'],
            $row['created_at'],
            $row['customer_name'],
            $row['game_name'],
            $row['item_name'],
            $row['user_id_game'],
            $row['server_id_game'],
            $row['modal_price'],
            $row['sell_price'],
            $row['profit'],
            $row['payment_method'],
            $row['order_status']
        ]);
    }
} elseif ($type === 'jurnal') {
    fputcsv($output, ['No Jurnal', 'Tanggal', 'Keterangan', 'Kode Akun', 'Nama Akun', 'Debit', 'Kredit']);
    $stmt = $db->prepare("SELECT je.journal_no, je.entry_date, je.description, a.code, a.name, ji.debit, ji.credit 
                          FROM journal_items ji 
                          JOIN journal_entries je ON ji.journal_id = je.id 
                          JOIN accounts a ON ji.account_id = a.id 
                          WHERE je.entry_date BETWEEN ? AND ? 
                          ORDER BY je.entry_date ASC, je.id ASC");
    $stmt->execute([$startDate, $endDate]);
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['journal_no'],
            $row['entry_date'],
            $row['description'],
            $row['code'],
            $row['name'],
            $row['debit'],
            $row['credit']
        ]);
    }
} elseif ($type === 'neraca_saldo') {
    fputcsv($output, ['Kode Akun', 'Nama Akun', 'Kelompok', 'Saldo Normal', 'Debit', 'Kredit']);
    $accounts = $db->query("SELECT * FROM accounts ORDER BY code ASC")->fetchAll();
    foreach ($accounts as $acc) {
        $bal = get_account_balance($db, (int)$acc['id'], $date);
        $deb = ($acc['normal_balance'] === 'DEBIT') ? max($bal, 0) : max(-$bal, 0);
        $crd = ($acc['normal_balance'] === 'KREDIT') ? max($bal, 0) : max(-$bal, 0);
        fputcsv($output, [$acc['code'], $acc['name'], $acc['type'], $acc['normal_balance'], $deb, $crd]);
    }
} elseif ($type === 'per_game') {
    fputcsv($output, ['Nama Game', 'Publisher', 'Total Pesanan', 'Total Omzet', 'Total Modal', 'Total Laba', 'Margin Persen']);
    $sql = "SELECT gc.name, gc.publisher, COUNT(o.id) as orders, COALESCE(SUM(o.sell_price),0) as omzet, COALESCE(SUM(o.modal_price),0) as modal, COALESCE(SUM(o.profit),0) as profit 
            FROM game_categories gc 
            LEFT JOIN topup_orders o ON gc.id = o.category_id AND DATE(o.created_at) BETWEEN ? AND ? AND o.order_status = 'BERHASIL' 
            GROUP BY gc.id, gc.name, gc.publisher";
    $stmt = $db->prepare($sql);
    $stmt->execute([$startDate, $endDate]);
    while ($row = $stmt->fetch()) {
        $omzet = (float)$row['omzet'];
        $profit = (float)$row['profit'];
        $margin = ($omzet > 0) ? round(($profit / $omzet) * 100, 1) : 0;
        fputcsv($output, [$row['name'], $row['publisher'], $row['orders'], $omzet, $row['modal'], $profit, $margin . '%']);
    }
}

fclose($output);
exit;
