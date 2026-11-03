<?php
/**
 * SmartAcc Hub - Top Up Orders Management
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();
$currentUser = current_user();

// Handle Status Change Action
if (isset($_GET['action']) && $_GET['action'] === 'update_status' && isset($_GET['id']) && isset($_GET['new_status'])) {
    $orderId = (int)$_GET['id'];
    $newStatus = strtoupper($_GET['new_status']);
    $validStatuses = ['PENDING', 'DIPROSES', 'BERHASIL', 'GAGAL'];

    if (in_array($newStatus, $validStatuses)) {
        // Fetch existing order
        $stmtOrd = $db->prepare("SELECT * FROM topup_orders WHERE id = ?");
        $stmtOrd->execute([$orderId]);
        $order = $stmtOrd->fetch();

        if ($order) {
            $oldStatus = $order['order_status'];
            $stmtUp = $db->prepare("UPDATE topup_orders SET order_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUp->execute([$newStatus, $orderId]);

            // If updated to BERHASIL and wasn't BERHASIL before, create accounting journal!
            if ($newStatus === 'BERHASIL' && $oldStatus !== 'BERHASIL') {
                $accKas = $db->query("SELECT id FROM accounts WHERE code='1101'")->fetchColumn();
                $accBca = $db->query("SELECT id FROM accounts WHERE code='1102'")->fetchColumn();
                $accMandiri = $db->query("SELECT id FROM accounts WHERE code='1103'")->fetchColumn();
                $accSup = $db->query("SELECT id FROM accounts WHERE code='1104'")->fetchColumn();
                $accPiutang = $db->query("SELECT id FROM accounts WHERE code='1105'")->fetchColumn();
                $accPenjualan = $db->query("SELECT id FROM accounts WHERE code='4101'")->fetchColumn();
                $accHpp = $db->query("SELECT id FROM accounts WHERE code='5101'")->fetchColumn();

                $payAcc = $accKas;
                if ($order['payment_method'] === 'BANK_BCA') $payAcc = $accBca;
                if ($order['payment_method'] === 'BANK_MANDIRI') $payAcc = $accMandiri;
                if ($order['payment_method'] === 'PIUTANG') $payAcc = $accPiutang;

                $jno = generate_journal_no();
                $today = date('Y-m-d');
                $items = [
                    ['account_id' => $payAcc, 'debit' => $order['sell_price'], 'credit' => 0, 'memo' => 'Penerimaan Penjualan ' . $order['invoice_no']],
                    ['account_id' => $accPenjualan, 'debit' => 0, 'credit' => $order['sell_price'], 'memo' => 'Penjualan ' . $order['game_name'] . ' ' . $order['item_name']],
                    ['account_id' => $accHpp, 'debit' => $order['modal_price'], 'credit' => 0, 'memo' => 'HPP ' . $order['invoice_no']],
                    ['account_id' => $accSup, 'debit' => 0, 'credit' => $order['modal_price'], 'memo' => 'Pemotongan Saldo Supplier']
                ];
                create_journal_entry($db, $jno, $today, 'TOPUP', $order['invoice_no'], 'Penjualan Topup ' . $order['game_name'] . ' (' . $order['invoice_no'] . ')', $items, $currentUser['username']);

                // Deduct supplier balance
                if ($order['supplier_id']) {
                    $db->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ?")->execute([$order['modal_price'], $order['supplier_id']]);
                }
            }

            log_activity('UPDATE_ORDER_STATUS', "Mengubah status pesanan {$order['invoice_no']} dari {$oldStatus} ke {$newStatus}");
            set_flash('success', "Status pesanan {$order['invoice_no']} berhasil diubah menjadi {$newStatus}.");
        }
    }
    header('Location: ' . BASE_URL . '/index.php?page=topup');
    exit;
}

// Handle New Order Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    $gameId = (int)$_POST['category_id'];
    $productId = (int)$_POST['product_id'];
    $userIdGame = trim($_POST['user_id_game'] ?? '');
    $serverIdGame = trim($_POST['server_id_game'] ?? '');
    $customerName = trim($_POST['customer_name'] ?? 'Pelanggan Umum');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'KAS';
    $orderStatus = $_POST['order_status'] ?? 'BERHASIL';
    $supplierId = (int)($_POST['supplier_id'] ?? 1);
    $notes = trim($_POST['notes'] ?? '');

    // Fetch Game & Product Details
    $stmtG = $db->prepare("SELECT name FROM game_categories WHERE id = ?");
    $stmtG->execute([$gameId]);
    $gameName = $stmtG->fetchColumn() ?: 'Game';

    $stmtP = $db->prepare("SELECT item_name, modal_price, sell_price FROM game_products WHERE id = ?");
    $stmtP->execute([$productId]);
    $prod = $stmtP->fetch();

    if ($prod) {
        $modal = (float)$prod['modal_price'];
        $sell = (float)$prod['sell_price'];
        $profit = $sell - $modal;
        $invNo = generate_invoice_no();

        // Customer handling
        $stmtC = $db->prepare("SELECT id FROM customers WHERE name = ? LIMIT 1");
        $stmtC->execute([$customerName]);
        $custId = $stmtC->fetchColumn();
        if (!$custId) {
            $db->prepare("INSERT INTO customers (name, phone) VALUES (?, ?)")->execute([$customerName, $customerPhone]);
            $custId = $db->lastInsertId();
        }

        $paymentStatus = ($paymentMethod === 'PIUTANG') ? 'BELUM_LUNAS' : 'LUNAS';

        $stmtIns = $db->prepare("INSERT INTO topup_orders 
            (invoice_no, customer_id, customer_name, category_id, product_id, game_name, item_name, user_id_game, server_id_game, modal_price, sell_price, profit, payment_method, payment_status, order_status, supplier_id, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmtIns->execute([
            $invNo, $custId, $customerName, $gameId, $productId, $gameName, $prod['item_name'],
            $userIdGame, $serverIdGame, $modal, $sell, $profit, $paymentMethod, $paymentStatus, $orderStatus, $supplierId, $notes, $currentUser['username']
        ]);

        // If order status is BERHASIL, record in Accounting General Journal & reduce supplier deposit
        if ($orderStatus === 'BERHASIL') {
            $accKas = $db->query("SELECT id FROM accounts WHERE code='1101'")->fetchColumn();
            $accBca = $db->query("SELECT id FROM accounts WHERE code='1102'")->fetchColumn();
            $accMandiri = $db->query("SELECT id FROM accounts WHERE code='1103'")->fetchColumn();
            $accSup = $db->query("SELECT id FROM accounts WHERE code='1104'")->fetchColumn();
            $accPiutang = $db->query("SELECT id FROM accounts WHERE code='1105'")->fetchColumn();
            $accPenjualan = $db->query("SELECT id FROM accounts WHERE code='4101'")->fetchColumn();
            $accHpp = $db->query("SELECT id FROM accounts WHERE code='5101'")->fetchColumn();

            $payAcc = $accKas;
            if ($paymentMethod === 'BANK_BCA') $payAcc = $accBca;
            if ($paymentMethod === 'BANK_MANDIRI') $payAcc = $accMandiri;
            if ($paymentMethod === 'PIUTANG') $payAcc = $accPiutang;

            $jno = generate_journal_no();
            $today = date('Y-m-d');
            $items = [
                ['account_id' => $payAcc, 'debit' => $sell, 'credit' => 0, 'memo' => 'Penerimaan Penjualan ' . $invNo],
                ['account_id' => $accPenjualan, 'debit' => 0, 'credit' => $sell, 'memo' => 'Penjualan ' . $gameName . ' ' . $prod['item_name']],
                ['account_id' => $accHpp, 'debit' => $modal, 'credit' => 0, 'memo' => 'HPP ' . $invNo],
                ['account_id' => $accSup, 'debit' => 0, 'credit' => $modal, 'memo' => 'Potong Saldo Supplier']
            ];
            create_journal_entry($db, $jno, $today, 'TOPUP', $invNo, 'Penjualan Topup ' . $gameName . ' (' . $invNo . ')', $items, $currentUser['username']);

            // Deduct supplier balance
            if ($supplierId) {
                $db->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ?")->execute([$modal, $supplierId]);
            }
        }

        // If PIUTANG, create debt record
        if ($paymentMethod === 'PIUTANG') {
            $db->prepare("INSERT INTO debts (debt_no, type, contact_type, contact_name, amount, notes) VALUES (?, 'PIUTANG', 'CUSTOMER', ?, ?, ?)")
               ->execute(['P-' . $invNo, $customerName, $sell, 'Piutang Top Up Invoice ' . $invNo]);
        }

        log_activity('CREATE_TOPUP_ORDER', "Pesanan baru {$invNo} ({$gameName} - {$prod['item_name']}) senilai " . format_rupiah($sell));
        set_flash('success', "Pesanan {$invNo} berhasil dibuat dengan laba " . format_rupiah($profit) . "!");
        header('Location: ' . BASE_URL . '/index.php?page=topup');
        exit;
    }
}

// Filter query
$statusFilter = $_GET['status'] ?? '';
$gameFilter = $_GET['game_id'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM topup_orders WHERE 1=1";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND order_status = ?";
    $params[] = $statusFilter;
}
if (!empty($gameFilter)) {
    $sql .= " AND category_id = ?";
    $params[] = $gameFilter;
}
if (!empty($search)) {
    $sql .= " AND (invoice_no LIKE ? OR customer_name LIKE ? OR user_id_game LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get games and products for dropdown
$categories = $db->query("SELECT * FROM game_categories WHERE status='active' ORDER BY name ASC")->fetchAll();
$allProducts = $db->query("SELECT p.*, c.name as category_name FROM game_products p JOIN game_categories c ON p.category_id = c.id WHERE p.status='active' ORDER BY p.category_id, p.sell_price ASC")->fetchAll();
$suppliers = $db->query("SELECT id, name, balance FROM suppliers WHERE status='active'")->fetchAll();

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="card-box">
    <div class="card-box-header">
        <div class="card-box-title">
            <i class="fas fa-shopping-cart" style="color:var(--primary);"></i>
            Daftar Pesanan & Riwayat Top Up Game
        </div>
        <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('newOrderModal')">
                <i class="fas fa-plus-circle"></i> Pesanan Baru
            </button>
            <a href="<?= BASE_URL ?>/index.php?page=export&type=topup" class="btn btn-outline btn-sm">
                <i class="fas fa-file-excel"></i> Ekspor CSV
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="" style="display:grid;grid-template-columns: 2fr 1fr 1fr auto;gap:12px;margin-bottom:20px;">
        <input type="hidden" name="page" value="topup">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Cari No Invoice, Nama Pelanggan, atau User ID...">
        
        <select name="game_id" class="form-select">
            <option value="">-- Semua Game --</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $gameFilter == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="form-select">
            <option value="">-- Semua Status --</option>
            <option value="PENDING" <?= $statusFilter === 'PENDING' ? 'selected' : '' ?>>Pending</option>
            <option value="DIPROSES" <?= $statusFilter === 'DIPROSES' ? 'selected' : '' ?>>Diproses</option>
            <option value="BERHASIL" <?= $statusFilter === 'BERHASIL' ? 'selected' : '' ?>>Berhasil</option>
            <option value="GAGAL" <?= $statusFilter === 'GAGAL' ? 'selected' : '' ?>>Gagal</option>
        </select>

        <button type="submit" class="btn btn-outline">
            <i class="fas fa-filter"></i> Filter
        </button>
    </form>

    <!-- Orders Table -->
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Tanggal/Waktu</th>
                    <th>Pelanggan</th>
                    <th>Game & Item</th>
                    <th>Akun Target</th>
                    <th>Harga Modal</th>
                    <th>Harga Jual</th>
                    <th>Laba (Untung)</th>
                    <th>Pembayaran</th>
                    <th>Status Pesanan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="11" style="text-align:center;padding:40px;color:var(--text-muted);">Tidak ada pesanan yang sesuai filter.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><strong class="text-mono"><?= htmlspecialchars($o['invoice_no']) ?></strong></td>
                            <td style="font-size:0.8rem;color:var(--text-muted);"><?= format_datetime($o['created_at']) ?></td>
                            <td><?= htmlspecialchars($o['customer_name']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($o['game_name']) ?></strong><br>
                                <span style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($o['item_name']) ?></span>
                            </td>
                            <td class="text-mono" style="font-size:0.8rem;">
                                ID: <strong><?= htmlspecialchars($o['user_id_game']) ?></strong>
                                <?= $o['server_id_game'] ? '<br><small>Zone: ' . htmlspecialchars($o['server_id_game']) . '</small>' : '' ?>
                            </td>
                            <td class="text-mono" style="color:var(--text-muted);"><?= format_rupiah($o['modal_price']) ?></td>
                            <td class="text-mono font-weight-bold"><?= format_rupiah($o['sell_price']) ?></td>
                            <td class="text-mono" style="color:var(--success);font-weight:700;">+<?= format_rupiah($o['profit']) ?></td>
                            <td>
                                <span style="font-size:0.75rem;font-weight:600;padding:2px 6px;background:var(--bg-card-subtle);border-radius:4px;">
                                    <?= htmlspecialchars($o['payment_method']) ?>
                                </span>
                            </td>
                            <td><?= render_status_badge($o['order_status']) ?></td>
                            <td>
                                <!-- Quick Status Switcher Dropdown -->
                                <div style="display:flex;gap:4px;">
                                    <?php if ($o['order_status'] !== 'BERHASIL'): ?>
                                        <a href="<?= BASE_URL ?>/index.php?page=topup&action=update_status&id=<?= $o['id'] ?>&new_status=BERHASIL" 
                                           class="btn btn-success btn-sm" title="Tandai Berhasil" onclick="return confirm('Konfirmasi pesanan selesai & catat ke jurnal akuntansi?')">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($o['order_status'] === 'PENDING'): ?>
                                        <a href="<?= BASE_URL ?>/index.php?page=topup&action=update_status&id=<?= $o['id'] ?>&new_status=DIPROSES" 
                                           class="btn btn-outline btn-sm" title="Proses Pesanan">
                                            <i class="fas fa-play"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($o['order_status'] !== 'GAGAL' && $o['order_status'] !== 'BERHASIL'): ?>
                                        <a href="<?= BASE_URL ?>/index.php?page=topup&action=update_status&id=<?= $o['id'] ?>&new_status=GAGAL" 
                                           class="btn btn-danger btn-sm" title="Tandai Gagal" onclick="return confirm('Tandai pesanan ini gagal?')">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-outline btn-sm" onclick='showReceipt(<?= json_encode($o) ?>)' title="Lihat Struk">
                                        <i class="fas fa-receipt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Input Pesanan Baru -->
<div class="modal-overlay" id="newOrderModal">
    <div class="modal-container modal-lg">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-plus-circle me-2" style="color:var(--primary);"></i> Buat Transaksi Top Up Baru</h3>
            <button type="button" class="btn-close" onclick="closeModal('newOrderModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="submit_order" value="1">
            <div class="modal-body">
                <!-- Step 1: Pilih Game -->
                <div class="form-group">
                    <label class="form-label">1. Pilih Game</label>
                    <div class="game-grid">
                        <?php foreach ($categories as $index => $cat): ?>
                            <div class="game-item-card <?= $index === 0 ? 'selected' : '' ?>" onclick="selectGame(<?= $cat['id'] ?>, this)">
                                <div class="game-item-icon">
                                    <i class="fas fa-gamepad"></i>
                                </div>
                                <div class="game-item-title"><?= htmlspecialchars($cat['name']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="category_id" id="selectedCategoryId" value="<?= $categories[0]['id'] ?? 1 ?>">
                </div>

                <!-- Step 2: Pilih Nominal Diamond & Item -->
                <div class="form-group">
                    <label class="form-label" for="productId">2. Pilih Paket / Nominal Diamond</label>
                    <select name="product_id" id="productId" class="form-select" required onchange="calculateProfit()">
                        <?php foreach ($allProducts as $p): ?>
                            <option value="<?= $p['id'] ?>" 
                                    data-category="<?= $p['category_id'] ?>" 
                                    data-modal="<?= $p['modal_price'] ?>" 
                                    data-sell="<?= $p['sell_price'] ?>">
                                <?= htmlspecialchars($p['item_name']) ?> - Jual: <?= format_rupiah($p['sell_price']) ?> (Modal: <?= format_rupiah($p['modal_price']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Live Profit Summary Box -->
                <div style="background:var(--bg-card-subtle);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:14px;margin-bottom:18px;display:grid;grid-template-columns:repeat(3, 1fr);text-align:center;">
                    <div>
                        <div style="font-size:0.75rem;color:var(--text-muted);">Harga Modal (COGS):</div>
                        <div id="displayModal" style="font-weight:700;font-size:1.05rem;" class="text-mono">Rp 0</div>
                    </div>
                    <div>
                        <div style="font-size:0.75rem;color:var(--text-muted);">Harga Jual Pelanggan:</div>
                        <div id="displaySell" style="font-weight:700;font-size:1.05rem;color:var(--primary);" class="text-mono">Rp 0</div>
                    </div>
                    <div>
                        <div style="font-size:0.75rem;color:var(--text-muted);">Margin Keuntungan:</div>
                        <div id="displayProfit" style="font-weight:800;font-size:1.1rem;color:var(--success);" class="text-mono">+Rp 0</div>
                    </div>
                </div>

                <!-- Step 3: Akun Game Pelanggan -->
                <div style="display:grid;grid-template-columns: 1fr 1fr;gap:16px;">
                    <div class="form-group">
                        <label class="form-label" for="userIdGame">User ID Game Pelanggan *</label>
                        <input type="text" name="user_id_game" id="userIdGame" class="form-control" placeholder="Contoh: 12345678" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="serverIdGame">Server / Zone ID (Opsional)</label>
                        <input type="text" name="server_id_game" id="serverIdGame" class="form-control" placeholder="Contoh: 2024 atau Asia">
                    </div>
                </div>

                <!-- Step 4: Data Pelanggan & Pembayaran -->
                <div style="display:grid;grid-template-columns: 1fr 1fr;gap:16px;">
                    <div class="form-group">
                        <label class="form-label" for="customerName">Nama Pelanggan</label>
                        <input type="text" name="customer_name" id="customerName" class="form-control" placeholder="Nama Pelanggan" value="Pelanggan Umum">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="customerPhone">No. WhatsApp / HP</label>
                        <input type="text" name="customer_phone" id="customerPhone" class="form-control" placeholder="08xxxxxxxxxx">
                    </div>
                </div>

                <div style="display:grid;grid-template-columns: 1fr 1fr 1fr;gap:16px;">
                    <div class="form-group">
                        <label class="form-label" for="paymentMethod">Metode Pembayaran</label>
                        <select name="payment_method" id="paymentMethod" class="form-select">
                            <option value="KAS">Kas Tunai Toko</option>
                            <option value="BANK_BCA">Bank BCA</option>
                            <option value="BANK_MANDIRI">Bank Mandiri</option>
                            <option value="PIUTANG">Piutang (Belum Bayar)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="orderStatus">Status Pesanan</label>
                        <select name="order_status" id="orderStatus" class="form-select">
                            <option value="BERHASIL">Berhasil (Selesai)</option>
                            <option value="DIPROSES">Diproses</option>
                            <option value="PENDING">Pending</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="supplierId">Server Supplier</label>
                        <select name="supplier_id" id="supplierId" class="form-select">
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?> (Saldo: <?= format_rupiah($sup['balance']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="notes">Catatan Tambahan</label>
                    <input type="text" name="notes" id="notes" class="form-control" placeholder="Catatan transaksi bila ada...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('newOrderModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle"></i> Simpan Transaksi</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Struk Pembelian / Nota Digital -->
<div class="modal-overlay" id="receiptModal">
    <div class="modal-container" style="max-width:440px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-receipt me-2"></i> Struk Transaksi Top Up</h3>
            <button type="button" class="btn-close" onclick="closeModal('receiptModal')">&times;</button>
        </div>
        <div class="modal-body" id="receiptContent" style="font-family:'Courier New', monospace;font-size:0.88rem;line-height:1.4;">
            <!-- Receipt rendered via JS -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i> Cetak Struk
            </button>
            <button type="button" class="btn btn-primary btn-sm" onclick="closeModal('receiptModal')">Tutup</button>
        </div>
    </div>
</div>

<script>
function selectGame(categoryId, el) {
    document.querySelectorAll('.game-item-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selectedCategoryId').value = categoryId;

    // Filter products
    const select = document.getElementById('productId');
    const options = select.querySelectorAll('option');
    let firstMatch = null;

    options.forEach(opt => {
        if (opt.getAttribute('data-category') == categoryId) {
            opt.style.display = '';
            if (!firstMatch) firstMatch = opt;
        } else {
            opt.style.display = 'none';
        }
    });

    if (firstMatch) {
        select.value = firstMatch.value;
    }
    calculateProfit();
}

function calculateProfit() {
    const select = document.getElementById('productId');
    const selected = select.options[select.selectedIndex];
    if (!selected) return;

    const modal = parseFloat(selected.getAttribute('data-modal') || 0);
    const sell = parseFloat(selected.getAttribute('data-sell') || 0);
    const profit = sell - modal;

    document.getElementById('displayModal').textContent = 'Rp ' + modal.toLocaleString('id-ID');
    document.getElementById('displaySell').textContent = 'Rp ' + sell.toLocaleString('id-ID');
    document.getElementById('displayProfit').textContent = '+Rp ' + profit.toLocaleString('id-ID');
}

function showReceipt(order) {
    const content = `
        <div style="text-align:center;margin-bottom:12px;border-bottom:1px dashed var(--border-color);padding-bottom:10px;">
            <div style="font-weight:bold;font-size:1.1rem;"><?= htmlspecialchars(get_setting('company_name', 'SmartAcc Game Store')) ?></div>
            <div style="font-size:0.75rem;"><?= htmlspecialchars(get_setting('company_tagline', 'Top Up Game Terpercaya')) ?></div>
            <div style="font-size:0.75rem;">Telp: <?= htmlspecialchars(get_setting('company_phone', '0812-3456-7890')) ?></div>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <span>No Invoice:</span>
            <strong>\${order.invoice_no}</strong>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <span>Waktu:</span>
            <span>\${order.created_at}</span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <span>Pelanggan:</span>
            <span>\${order.customer_name}</span>
        </div>
        <div style="border-top:1px dashed var(--border-color);margin:8px 0;padding-top:8px;">
            <div style="font-weight:bold;">\${order.game_name}</div>
            <div style="display:flex;justify-content:space-between;">
                <span>\${order.item_name}</span>
                <strong>Rp \${parseFloat(order.sell_price).toLocaleString('id-ID')}</strong>
            </div>
            <div style="font-size:0.75rem;color:var(--text-muted);">Target: \${order.user_id_game} \${order.server_id_game ? '(' + order.server_id_game + ')' : ''}</div>
        </div>
        <div style="border-top:1px dashed var(--border-color);margin:8px 0;padding-top:8px;">
            <div style="display:flex;justify-content:space-between;">
                <span>Total Bayar:</span>
                <strong style="font-size:1.05rem;">Rp \${parseFloat(order.sell_price).toLocaleString('id-ID')}</strong>
            </div>
            <div style="display:flex;justify-content:space-between;">
                <span>Metode:</span>
                <span>\${order.payment_method}</span>
            </div>
            <div style="display:flex;justify-content:space-between;">
                <span>Status:</span>
                <strong>\${order.order_status}</strong>
            </div>
        </div>
        <div style="text-align:center;margin-top:16px;font-size:0.75rem;border-top:1px dashed var(--border-color);padding-top:8px;">
            Terima kasih telah melakukan top up game di toko kami!<br>Item otomatis masuk ke akun Anda.
        </div>
    `;
    document.getElementById('receiptContent').innerHTML = content;
    openModal('receiptModal');
}

// Auto open modal if ?action=new in URL
document.addEventListener('DOMContentLoaded', function() {
    // Initial calculation
    const select = document.getElementById('productId');
    const firstCat = document.getElementById('selectedCategoryId').value;
    selectGame(firstCat, document.querySelector('.game-item-card.selected') || document.querySelector('.game-item-card'));

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'new') {
        openModal('newOrderModal');
    }
});
</script>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
