<?php
/**
 * SmartAcc Hub - Game & Diamond Products Management
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$db = Database::getConnection();
$currentUser = current_user();

// Handle Add / Edit Category (Game)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $code = strtoupper(trim($_POST['code']));
    $name = trim($_POST['name']);
    $publisher = trim($_POST['publisher'] ?? '');

    if (!empty($code) && !empty($name)) {
        if (!empty($_POST['cat_id'])) {
            $catId = (int)$_POST['cat_id'];
            $stmt = $db->prepare("UPDATE game_categories SET code = ?, name = ?, publisher = ? WHERE id = ?");
            $stmt->execute([$code, $name, $publisher, $catId]);
            log_activity('UPDATE_GAME', "Memperbarui data game {$name}");
            set_flash('success', "Data game {$name} berhasil diperbarui.");
        } else {
            $stmt = $db->prepare("INSERT INTO game_categories (code, name, publisher, icon) VALUES (?, ?, ?, 'gamepad')");
            $stmt->execute([$code, $name, $publisher]);
            log_activity('ADD_GAME', "Menambahkan game baru {$name}");
            set_flash('success', "Game baru {$name} berhasil ditambahkan.");
        }
    }
    header('Location: ' . BASE_URL . '/index.php?page=topup_products');
    exit;
}

// Handle Add / Edit Product (Nominal Diamond)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $catId = (int)$_POST['category_id'];
    $itemName = trim($_POST['item_name']);
    $nominal = trim($_POST['nominal']);
    $unit = trim($_POST['unit'] ?? 'Diamond');
    $modalPrice = (float)$_POST['modal_price'];
    $sellPrice = (float)$_POST['sell_price'];

    if ($catId > 0 && !empty($itemName) && $sellPrice > 0) {
        if (!empty($_POST['prod_id'])) {
            $prodId = (int)$_POST['prod_id'];
            $stmt = $db->prepare("UPDATE game_products SET category_id = ?, item_name = ?, nominal = ?, unit = ?, modal_price = ?, sell_price = ? WHERE id = ?");
            $stmt->execute([$catId, $itemName, $nominal, $unit, $modalPrice, $sellPrice, $prodId]);
            log_activity('UPDATE_PRODUCT', "Memperbarui produk {$itemName}");
            set_flash('success', "Paket {$itemName} berhasil diperbarui.");
        } else {
            $stmt = $db->prepare("INSERT INTO game_products (category_id, item_name, nominal, unit, modal_price, sell_price) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$catId, $itemName, $nominal, $unit, $modalPrice, $sellPrice]);
            log_activity('ADD_PRODUCT', "Menambahkan paket item {$itemName}");
            set_flash('success', "Paket {$itemName} berhasil ditambahkan.");
        }
    }
    header('Location: ' . BASE_URL . '/index.php?page=topup_products');
    exit;
}

// Handle Delete Product
if (isset($_GET['delete_prod'])) {
    $prodId = (int)$_GET['delete_prod'];
    $db->prepare("DELETE FROM game_products WHERE id = ?")->execute([$prodId]);
    log_activity('DELETE_PRODUCT', "Menghapus produk item ID {$prodId}");
    set_flash('warning', "Paket item berhasil dihapus.");
    header('Location: ' . BASE_URL . '/index.php?page=topup_products');
    exit;
}

// Fetch categories and products
$categories = $db->query("SELECT * FROM game_categories ORDER BY name ASC")->fetchAll();
$selectedCatId = (int)($_GET['cat_id'] ?? ($categories[0]['id'] ?? 0));

$stmtProd = $db->prepare("SELECT p.*, c.name as game_name FROM game_products p JOIN game_categories c ON p.category_id = c.id WHERE p.category_id = ? ORDER BY p.sell_price ASC");
$stmtProd->execute([$selectedCatId]);
$products = $stmtProd->fetchAll();

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div style="display:grid;grid-template-columns: 320px 1fr;gap:24px;">
    <!-- Left Column: Game Categories List -->
    <div class="card-box">
        <div class="card-box-header">
            <div class="card-box-title">
                <i class="fas fa-gamepad" style="color:var(--primary);"></i>
                Daftar Game
            </div>
            <button type="button" class="btn btn-outline btn-sm" onclick="openModal('addGameModal')">
                <i class="fas fa-plus"></i> Tambah
            </button>
        </div>

        <div style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($categories as $cat): ?>
                <?php
                $cnt = $db->query("SELECT COUNT(*) FROM game_products WHERE category_id = " . $cat['id'])->fetchColumn();
                $isActive = ($cat['id'] == $selectedCatId);
                ?>
                <a href="<?= BASE_URL ?>/index.php?page=topup_products&cat_id=<?= $cat['id'] ?>" 
                   style="padding:12px 14px;border-radius:var(--radius-md);border:1px solid <?= $isActive ? 'var(--primary)' : 'var(--border-color)' ?>;background:<?= $isActive ? 'var(--primary-light)' : 'var(--bg-card)' ?>;display:flex;align-items:center;justify-content:space-between;color:var(--text-primary);text-decoration:none;">
                    <div>
                        <div style="font-weight:700;font-size:0.92rem;color:<?= $isActive ? 'var(--primary)' : 'inherit' ?>;">
                            <?= htmlspecialchars($cat['name']) ?>
                        </div>
                        <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($cat['publisher']) ?></div>
                    </div>
                    <span class="status-badge badge-secondary" style="font-size:0.7rem;">
                        <?= $cnt ?> Item
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right Column: Products / Diamond Packages Table -->
    <div class="card-box">
        <div class="card-box-header">
            <div class="card-box-title">
                <i class="fas fa-gem" style="color:var(--info);"></i>
                Paket Diamond & Item Game
            </div>
            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addProductModal')">
                <i class="fas fa-plus-circle"></i> Tambah Paket Item
            </button>
        </div>

        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Nama Paket</th>
                        <th>Nominal</th>
                        <th>Unit</th>
                        <th>Harga Modal (Beli)</th>
                        <th>Harga Jual (Toko)</th>
                        <th>Margin Laba</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:35px;color:var(--text-muted);">
                                Belum ada produk diamond untuk game ini. Silakan klik Tambah Paket Item.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <?php $margin = (float)$p['sell_price'] - (float)$p['modal_price']; ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['item_name']) ?></strong></td>
                                <td class="text-mono"><?= htmlspecialchars($p['nominal']) ?></td>
                                <td><?= htmlspecialchars($p['unit']) ?></td>
                                <td class="text-mono"><?= format_rupiah($p['modal_price']) ?></td>
                                <td class="text-mono font-weight-bold" style="color:var(--primary);"><?= format_rupiah($p['sell_price']) ?></td>
                                <td class="text-mono" style="color:var(--success);font-weight:700;">+<?= format_rupiah($margin) ?></td>
                                <td><?= render_status_badge($p['status']) ?></td>
                                <td>
                                    <div style="display:flex;gap:4px;">
                                        <button type="button" class="btn btn-outline btn-sm" onclick='editProduct(<?= json_encode($p) ?>)' title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="<?= BASE_URL ?>/index.php?page=topup_products&delete_prod=<?= $p['id'] ?>" 
                                           class="btn btn-danger btn-sm" title="Hapus" onclick="return confirm('Hapus paket item ini?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Game -->
<div class="modal-overlay" id="addGameModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-gamepad me-2"></i> Tambah Kategori Game</h3>
            <button type="button" class="btn-close" onclick="closeModal('addGameModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="save_category" value="1">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="gameCode">Kode Singkatan Game *</label>
                    <input type="text" name="code" id="gameCode" class="form-control" placeholder="Contoh: MLBB / HOK / ROBLOX" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="gameName">Nama Lengkap Game *</label>
                    <input type="text" name="name" id="gameName" class="form-control" placeholder="Contoh: Honor of Kings" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="publisher">Publisher / Pengembang</label>
                    <input type="text" name="publisher" id="publisher" class="form-control" placeholder="Contoh: Level Infinite">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addGameModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Game</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Tambah/Edit Paket Diamond Product -->
<div class="modal-overlay" id="addProductModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title" id="prodModalTitle"><i class="fas fa-gem me-2"></i> Tambah Paket Item Diamond</h3>
            <button type="button" class="btn-close" onclick="closeModal('addProductModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="save_product" value="1">
            <input type="hidden" name="prod_id" id="formProdId" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="formCatId">Pilih Game</label>
                    <select name="category_id" id="formCatId" class="form-select" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $selectedCatId ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="formItemName">Nama Paket / Item *</label>
                    <input type="text" name="item_name" id="formItemName" class="form-control" placeholder="Contoh: 140 Diamonds atau Starlight Member" required>
                </div>
                <div style="display:grid;grid-template-columns: 1fr 1fr;gap:14px;">
                    <div class="form-group">
                        <label class="form-label" for="formNominal">Nominal Jumlah</label>
                        <input type="text" name="nominal" id="formNominal" class="form-control" placeholder="140" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="formUnit">Satuan / Unit</label>
                        <input type="text" name="unit" id="formUnit" class="form-control" placeholder="Diamond / UC / CP" value="Diamond">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns: 1fr 1fr;gap:14px;">
                    <div class="form-group">
                        <label class="form-label" for="formModalPrice">Harga Modal Supplier (Rp) *</label>
                        <input type="number" step="100" name="modal_price" id="formModalPrice" class="form-control" placeholder="18500" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="formSellPrice">Harga Jual Toko (Rp) *</label>
                        <input type="number" step="100" name="sell_price" id="formSellPrice" class="form-control" placeholder="21000" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addProductModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Paket</button>
            </div>
        </form>
    </div>
</div>

<script>
function editProduct(prod) {
    document.getElementById('prodModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i> Edit Paket Item Diamond';
    document.getElementById('formProdId').value = prod.id;
    document.getElementById('formCatId').value = prod.category_id;
    document.getElementById('formItemName').value = prod.item_name;
    document.getElementById('formNominal').value = prod.nominal;
    document.getElementById('formUnit').value = prod.unit;
    document.getElementById('formModalPrice').value = prod.modal_price;
    document.getElementById('formSellPrice').value = prod.sell_price;
    openModal('addProductModal');
}
</script>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
