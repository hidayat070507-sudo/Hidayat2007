<?php
/**
 * SmartAcc Hub - Schema & Auto Seeder
 */

require_once __DIR__ . '/database.php';

function init_database() {
    $db = Database::getConnection();

    // 1. Users Table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'kasir',
        email VARCHAR(100),
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Settings Table
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key_name VARCHAR(50) PRIMARY KEY,
        value_data TEXT
    )");

    // 3. Activity Logs Table
    $db->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        user_name VARCHAR(100),
        action VARCHAR(100) NOT NULL,
        description TEXT,
        ip_address VARCHAR(50),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 4. Accounts Table (COA)
    $db->exec("CREATE TABLE IF NOT EXISTS accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code VARCHAR(20) UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        type VARCHAR(30) NOT NULL, -- ASET, KEWAJIBAN, EKUITAS, PENDAPATAN, BEBAN_HPP, BEBAN_OPERASIONAL
        normal_balance VARCHAR(10) NOT NULL DEFAULT 'DEBIT', -- DEBIT or KREDIT
        is_cash_bank INTEGER NOT NULL DEFAULT 0,
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 5. Journal Entries Table
    $db->exec("CREATE TABLE IF NOT EXISTS journal_entries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        journal_no VARCHAR(50) UNIQUE NOT NULL,
        entry_date DATE NOT NULL,
        reference_type VARCHAR(50) DEFAULT 'MANUAL', -- TOPUP, KAS_MASUK, KAS_KELUAR, DEPOSIT, TRANSFER, PENYESUAIAN, MANUAL
        reference_id VARCHAR(50),
        description TEXT NOT NULL,
        created_by VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 6. Journal Items Table
    $db->exec("CREATE TABLE IF NOT EXISTS journal_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        journal_id INTEGER NOT NULL,
        account_id INTEGER NOT NULL,
        debit REAL DEFAULT 0,
        credit REAL DEFAULT 0,
        memo VARCHAR(255),
        FOREIGN KEY (journal_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
        FOREIGN KEY (account_id) REFERENCES accounts(id)
    )");

    // 7. Game Categories
    $db->exec("CREATE TABLE IF NOT EXISTS game_categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code VARCHAR(30) UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        publisher VARCHAR(100),
        icon VARCHAR(100),
        status VARCHAR(20) DEFAULT 'active'
    )");

    // 8. Game Products
    $db->exec("CREATE TABLE IF NOT EXISTS game_products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category_id INTEGER NOT NULL,
        item_name VARCHAR(150) NOT NULL,
        nominal VARCHAR(100) NOT NULL,
        unit VARCHAR(50) DEFAULT 'Diamond',
        modal_price REAL NOT NULL,
        sell_price REAL NOT NULL,
        status VARCHAR(20) DEFAULT 'active',
        FOREIGN KEY (category_id) REFERENCES game_categories(id) ON DELETE CASCADE
    )");

    // 9. Customers
    $db->exec("CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(30),
        email VARCHAR(100),
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 10. Suppliers
    $db->exec("CREATE TABLE IF NOT EXISTS suppliers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(30),
        email VARCHAR(100),
        balance REAL DEFAULT 0,
        status VARCHAR(20) DEFAULT 'active',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 11. Top Up Orders
    $db->exec("CREATE TABLE IF NOT EXISTS topup_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        invoice_no VARCHAR(50) UNIQUE NOT NULL,
        customer_id INTEGER,
        customer_name VARCHAR(100) NOT NULL,
        category_id INTEGER NOT NULL,
        product_id INTEGER NOT NULL,
        game_name VARCHAR(100) NOT NULL,
        item_name VARCHAR(150) NOT NULL,
        user_id_game VARCHAR(100) NOT NULL,
        server_id_game VARCHAR(100),
        modal_price REAL NOT NULL,
        sell_price REAL NOT NULL,
        profit REAL NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'KAS', -- KAS, BANK_BCA, BANK_MANDIRI, PIUTANG
        payment_status VARCHAR(30) DEFAULT 'LUNAS', -- LUNAS, BELUM_LUNAS
        order_status VARCHAR(30) DEFAULT 'BERHASIL', -- PENDING, DIPROSES, BERHASIL, GAGAL
        supplier_id INTEGER,
        notes TEXT,
        created_by VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 12. Cash Transactions (Kas Masuk & Kas Keluar)
    $db->exec("CREATE TABLE IF NOT EXISTS cash_transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        trans_no VARCHAR(50) UNIQUE NOT NULL,
        trans_type VARCHAR(20) NOT NULL, -- KAS_MASUK, KAS_KELUAR
        category VARCHAR(50) NOT NULL, -- BIAYA_OPERASIONAL, BIAYA_ADMIN, PENDAPATAN_LAIN, MODAL, LAIN_LAIN
        account_id INTEGER NOT NULL, -- Kas/Bank account
        contra_account_id INTEGER NOT NULL, -- Biaya / Pendapatan / Modal account
        amount REAL NOT NULL,
        trans_date DATE NOT NULL,
        description TEXT,
        created_by VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 13. Supplier Deposits
    $db->exec("CREATE TABLE IF NOT EXISTS supplier_deposits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        deposit_no VARCHAR(50) UNIQUE NOT NULL,
        supplier_id INTEGER NOT NULL,
        account_id INTEGER NOT NULL, -- Payment source (Bank / Kas)
        amount REAL NOT NULL,
        admin_fee REAL DEFAULT 0,
        total_deducted REAL NOT NULL,
        deposit_date DATE NOT NULL,
        status VARCHAR(20) DEFAULT 'BERHASIL',
        notes TEXT,
        created_by VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 14. Transfers between Accounts (Kas ke Bank, dsb.)
    $db->exec("CREATE TABLE IF NOT EXISTS transfers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        transfer_no VARCHAR(50) UNIQUE NOT NULL,
        from_account_id INTEGER NOT NULL,
        to_account_id INTEGER NOT NULL,
        amount REAL NOT NULL,
        fee REAL DEFAULT 0,
        transfer_date DATE NOT NULL,
        notes TEXT,
        created_by VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 15. Debts & Receivables (Utang & Piutang)
    $db->exec("CREATE TABLE IF NOT EXISTS debts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        debt_no VARCHAR(50) UNIQUE NOT NULL,
        type VARCHAR(20) NOT NULL, -- UTANG, PIUTANG
        contact_type VARCHAR(20) NOT NULL, -- CUSTOMER, SUPPLIER, LAINNYA
        contact_name VARCHAR(100) NOT NULL,
        amount REAL NOT NULL,
        paid_amount REAL DEFAULT 0,
        due_date DATE,
        status VARCHAR(20) DEFAULT 'BELUM_LUNAS', -- BELUM_LUNAS, SEBAGIAN, LUNAS
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed default data if users table is empty
    seed_default_data($db);
}

function seed_default_data($db) {
    $userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount > 0) {
        return; // Already seeded
    }

    // Default Users
    $stmtUser = $db->prepare("INSERT INTO users (username, password, full_name, role, email, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtUser->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Administrator Utama', 'admin', 'admin@smartacc.local', 'active']);
    $stmtUser->execute(['kasir', password_hash('kasir123', PASSWORD_DEFAULT), 'Staf Kasir & Penjualan', 'kasir', 'kasir@smartacc.local', 'active']);
    $stmtUser->execute(['keuangan', password_hash('keuangan123', PASSWORD_DEFAULT), 'Staf Akuntan & Keuangan', 'keuangan', 'keuangan@smartacc.local', 'active']);

    // Default Settings
    $settings = [
        'app_name' => 'SmartAcc Hub',
        'company_name' => 'SmartAcc Game Store',
        'company_tagline' => 'Pusat Top Up Game Terpercaya & Catatan Akuntansi Modern',
        'company_address' => 'Jl. Boulevard Digital No. 88, Jakarta Selatan',
        'company_phone' => '0812-3456-7890',
        'company_email' => 'support@smartacc.store',
        'currency' => 'Rp',
        'theme_default' => 'dark'
    ];
    $stmtSet = $db->prepare("INSERT INTO settings (key_name, value_data) VALUES (?, ?)");
    foreach ($settings as $k => $v) {
        $stmtSet->execute([$k, $v]);
    }

    // Standard Chart of Accounts (COA)
    $accounts = [
        // ASET
        ['1101', 'Kas Tunai Toko', 'ASET', 'DEBIT', 1, 'Kas fisik di kasir toko'],
        ['1102', 'Bank BCA Operasional', 'ASET', 'DEBIT', 1, 'Rekening Bank BCA utama'],
        ['1103', 'Bank Mandiri Bisnis', 'ASET', 'DEBIT', 1, 'Rekening Bank Mandiri bisnis'],
        ['1104', 'Saldo Supplier Top Up', 'ASET', 'DEBIT', 1, 'Deposit saldo di server supplier'],
        ['1105', 'Piutang Usaha Pelanggan', 'ASET', 'DEBIT', 0, 'Piutang pembelian topup pelanggan'],
        ['1201', 'Peralatan & Komputer Toko', 'ASET', 'DEBIT', 0, 'Aset tetap komputer dan router'],
        // KEWAJIBAN
        ['2101', 'Utang Usaha & Tagihan', 'KEWAJIBAN', 'KREDIT', 0, 'Kewajiban jangka pendek'],
        ['2102', 'Utang Supplier', 'KEWAJIBAN', 'KREDIT', 0, 'Tagihan saldo belum dibayar ke supplier'],
        // EKUITAS
        ['3101', 'Modal Awal Usaha', 'EKUITAS', 'KREDIT', 0, 'Modal setor pemilik usaha'],
        ['3201', 'Laba Ditahan', 'EKUITAS', 'KREDIT', 0, 'Akumulasi laba periode berjalan'],
        // PENDAPATAN
        ['4101', 'Pendapatan Penjualan Top Up', 'PENDAPATAN', 'KREDIT', 0, 'Omzet penjualan diamond & item game'],
        ['4201', 'Pendapatan Jasa & Biaya Admin', 'PENDAPATAN', 'KREDIT', 0, 'Fee admin transaksi'],
        ['4301', 'Pendapatan Lain-lain', 'PENDAPATAN', 'KREDIT', 0, 'Pemasukan di luar penjualan utama'],
        // BEBAN HPP
        ['5101', 'Beban Pokok Penjualan (HPP)', 'BEBAN_HPP', 'DEBIT', 0, 'Harga modal diamond game dari supplier'],
        // BEBAN OPERASIONAL
        ['6101', 'Beban Listrik, Air & Internet', 'BEBAN_OPERASIONAL', 'DEBIT', 0, 'Biaya utilitas toko bulanan'],
        ['6102', 'Beban Sewa Server & Domain', 'BEBAN_OPERASIONAL', 'DEBIT', 0, 'Biaya langganan cloud & server'],
        ['6103', 'Beban Gaji Karyawan', 'BEBAN_OPERASIONAL', 'DEBIT', 0, 'Gaji staf kasir & admin'],
        ['6104', 'Beban Admin Bank & Transfer', 'BEBAN_OPERASIONAL', 'DEBIT', 0, 'Biaya fee transfer bank'],
        ['6105', 'Beban Operasional Lainnya', 'BEBAN_OPERASIONAL', 'DEBIT', 0, 'Biaya konsumsi & perlengkapan']
    ];
    $stmtAcc = $db->prepare("INSERT INTO accounts (code, name, type, normal_balance, is_cash_bank, description) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($accounts as $acc) {
        $stmtAcc->execute($acc);
    }

    // Default Suppliers
    $stmtSup = $db->prepare("INSERT INTO suppliers (name, phone, email, balance, notes) VALUES (?, ?, ?, ?, ?)");
    $stmtSup->execute(['Digiflazz VIP Server', '081122334455', 'api@digiflazz.com', 2500000, 'Supplier API Topup Otomatis']);
    $stmtSup->execute(['Tokovoucher Indonesia', '081299887766', 'support@tokovoucher.id', 1200000, 'Supplier Voucher & Game']);

    // Default Customers
    $stmtCust = $db->prepare("INSERT INTO customers (name, phone, email, notes) VALUES (?, ?, ?, ?)");
    $stmtCust->execute(['Rian Hidayat', '081234567890', 'rian.h@gmail.com', 'Pelanggan setia Mobile Legends']);
    $stmtCust->execute(['Dika Pratama', '085678901234', 'dika99@yahoo.com', 'Member FF squad']);
    $stmtCust->execute(['Sarah Agustin', '087812345678', 'sarah.agustin@gmail.com', 'Player Genshin Impact']);
    $stmtCust->execute(['Pelanggan Umum', '-', '-', 'Guest walk-in customer']);

    // Game Categories
    $categories = [
        ['MLBB', 'Mobile Legends: Bang Bang', 'Moonton', 'gamepad', 'active'],
        ['FF', 'Free Fire', 'Garena', 'flame', 'active'],
        ['PUBGM', 'PUBG Mobile', 'Tencent', 'target', 'active'],
        ['GENSHIN', 'Genshin Impact', 'HoYoverse', 'sparkles', 'active'],
        ['VALORANT', 'Valorant', 'Riot Games', 'crosshair', 'active']
    ];
    $stmtCat = $db->prepare("INSERT INTO game_categories (code, name, publisher, icon, status) VALUES (?, ?, ?, ?, ?)");
    foreach ($categories as $cat) {
        $stmtCat->execute($cat);
    }

    // Game Products
    $products = [
        // MLBB (cat_id 1)
        [1, '86 Diamonds', '86', 'Diamond', 18500, 21000],
        [1, '172 Diamonds', '172', 'Diamond', 37000, 42000],
        [1, '257 Diamonds', '257', 'Diamond', 55500, 62000],
        [1, '706 Diamonds', '706', 'Diamond', 148000, 165000],
        [1, 'Weekly Diamond Pass', '1 Minggu', 'Pass', 26500, 30000],
        // FF (cat_id 2)
        [2, '100 Diamonds', '100', 'Diamond', 13500, 15500],
        [2, '140 Diamonds', '140', 'Diamond', 18500, 21000],
        [2, '355 Diamonds', '355', 'Diamond', 46000, 52000],
        [2, '720 Diamonds', '720', 'Diamond', 92000, 102000],
        [2, 'Membership Mingguan', '1 Minggu', 'Member', 28000, 32000],
        // PUBGM (cat_id 3)
        [3, '60 UC', '60', 'UC', 13500, 15000],
        [3, '325 UC', '325', 'UC', 68000, 75000],
        [3, '660 UC', '660', 'UC', 136000, 150000],
        // GENSHIN (cat_id 4)
        [4, '60 Genesis Crystal', '60', 'Crystal', 15000, 17000],
        [4, '330 Genesis Crystal', '330', 'Crystal', 72000, 80000],
        [4, 'Blessing of Welkin Moon', '30 Hari', 'Pass', 74000, 82000],
        // VALORANT (cat_id 5)
        [5, '475 Valorant Points', '475', 'VP', 50000, 56000],
        [5, '1000 Valorant Points', '1000', 'VP', 105000, 118000]
    ];
    $stmtProd = $db->prepare("INSERT INTO game_products (category_id, item_name, nominal, unit, modal_price, sell_price) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($products as $p) {
        $stmtProd->execute($p);
    }

    // Seed Initial Capital Journal (Modal Awal: Kas 5.000.000, Bank BCA 15.000.000, Saldo Supplier 3.700.000 = Modal 23.700.000)
    $today = date('Y-m-d');
    $db->exec("INSERT INTO journal_entries (journal_no, entry_date, reference_type, reference_id, description, created_by) 
               VALUES ('JU-" . date('Ymd') . "-0001', '{$today}', 'MODAL_AWAL', 'SETUP', 'Setoran Modal Awal Usaha Pemilik', 'admin')");
    $journalId = $db->lastInsertId();

    $accKas = $db->query("SELECT id FROM accounts WHERE code='1101'")->fetchColumn();
    $accBca = $db->query("SELECT id FROM accounts WHERE code='1102'")->fetchColumn();
    $accSup = $db->query("SELECT id FROM accounts WHERE code='1104'")->fetchColumn();
    $accModal = $db->query("SELECT id FROM accounts WHERE code='3101'")->fetchColumn();

    $stmtJItem = $db->prepare("INSERT INTO journal_items (journal_id, account_id, debit, credit, memo) VALUES (?, ?, ?, ?, ?)");
    $stmtJItem->execute([$journalId, $accKas, 5000000, 0, 'Kas Tunai di Toko']);
    $stmtJItem->execute([$journalId, $accBca, 15000000, 0, 'Saldo Bank BCA']);
    $stmtJItem->execute([$journalId, $accSup, 3700000, 0, 'Deposit Saldo di Supplier Topup']);
    $stmtJItem->execute([$journalId, $accModal, 0, 23700000, 'Modal Awal Disetor']);

    // Seed Sample TopUp Orders & Auto Journal
    $sampleOrders = [
        [
            'inv' => 'INV-' . date('ymd') . '-001',
            'cust_id' => 1,
            'cust_name' => 'Rian Hidayat',
            'cat_id' => 1,
            'prod_id' => 1,
            'game' => 'Mobile Legends: Bang Bang',
            'item' => '86 Diamonds',
            'uid' => '12345678',
            'zone' => '2024',
            'modal' => 18500,
            'sell' => 21000,
            'profit' => 2500,
            'pay_method' => 'KAS',
            'status' => 'BERHASIL',
            'time' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ],
        [
            'inv' => 'INV-' . date('ymd') . '-002',
            'cust_id' => 2,
            'cust_name' => 'Dika Pratama',
            'cat_id' => 2,
            'prod_id' => 8,
            'game' => 'Free Fire',
            'item' => '355 Diamonds',
            'uid' => '87654321',
            'zone' => '-',
            'modal' => 46000,
            'sell' => 52000,
            'profit' => 6000,
            'pay_method' => 'BANK_BCA',
            'status' => 'BERHASIL',
            'time' => date('Y-m-d H:i:s', strtotime('-1 hours'))
        ],
        [
            'inv' => 'INV-' . date('ymd') . '-003',
            'cust_id' => 3,
            'cust_name' => 'Sarah Agustin',
            'cat_id' => 4,
            'prod_id' => 16,
            'game' => 'Genshin Impact',
            'item' => 'Blessing of Welkin Moon',
            'uid' => '801234567',
            'zone' => 'Asia',
            'modal' => 74000,
            'sell' => 82000,
            'profit' => 8000,
            'pay_method' => 'BANK_BCA',
            'status' => 'BERHASIL',
            'time' => date('Y-m-d H:i:s', strtotime('-30 minutes'))
        ],
        [
            'inv' => 'INV-' . date('ymd') . '-004',
            'cust_id' => 4,
            'cust_name' => 'Pelanggan Umum',
            'cat_id' => 1,
            'prod_id' => 5,
            'game' => 'Mobile Legends: Bang Bang',
            'item' => 'Weekly Diamond Pass',
            'uid' => '45678912',
            'zone' => '2123',
            'modal' => 26500,
            'sell' => 30000,
            'profit' => 3500,
            'pay_method' => 'KAS',
            'status' => 'DIPROSES',
            'time' => date('Y-m-d H:i:s', strtotime('-10 minutes'))
        ]
    ];

    $accPenjualan = $db->query("SELECT id FROM accounts WHERE code='4101'")->fetchColumn();
    $accHpp = $db->query("SELECT id FROM accounts WHERE code='5101'")->fetchColumn();

    $stmtOrder = $db->prepare("INSERT INTO topup_orders 
        (invoice_no, customer_id, customer_name, category_id, product_id, game_name, item_name, user_id_game, server_id_game, modal_price, sell_price, profit, payment_method, payment_status, order_status, supplier_id, created_by, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'LUNAS', ?, 1, 'kasir', ?, ?)");

    foreach ($sampleOrders as $idx => $ord) {
        $stmtOrder->execute([
            $ord['inv'], $ord['cust_id'], $ord['cust_name'], $ord['cat_id'], $ord['prod_id'],
            $ord['game'], $ord['item'], $ord['uid'], $ord['zone'],
            $ord['modal'], $ord['sell'], $ord['profit'], $ord['pay_method'], $ord['status'],
            $ord['time'], $ord['time']
        ]);

        if ($ord['status'] === 'BERHASIL') {
            // Create Journal
            $payAcc = ($ord['pay_method'] === 'BANK_BCA') ? $accBca : $accKas;
            $jno = 'JU-' . date('Ymd') . '-0' . str_pad($idx + 2, 3, '0', STR_PAD_LEFT);
            $db->exec("INSERT INTO journal_entries (journal_no, entry_date, reference_type, reference_id, description, created_by)
                       VALUES ('{$jno}', '{$today}', 'TOPUP', '{$ord['inv']}', 'Penjualan Topup {$ord['game']} - {$ord['item']} ({$ord['inv']})', 'kasir')");
            $jid = $db->lastInsertId();

            // 1. Debit Kas/Bank, Kredit Penjualan
            $stmtJItem->execute([$jid, $payAcc, $ord['sell'], 0, 'Penerimaan Penjualan ' . $ord['inv']]);
            $stmtJItem->execute([$jid, $accPenjualan, 0, $ord['sell'], 'Omzet Topup ' . $ord['game']]);

            // 2. Debit HPP, Kredit Saldo Supplier
            $stmtJItem->execute([$jid, $accHpp, $ord['modal'], 0, 'HPP Topup ' . $ord['game']]);
            $stmtJItem->execute([$jid, $accSup, 0, $ord['modal'], 'Potong Saldo Supplier']);
        }
    }

    // Seed Operational Expense (Biaya Internet & Listrik Rp 350.000)
    $accBebanInternet = $db->query("SELECT id FROM accounts WHERE code='6101'")->fetchColumn();
    $db->exec("INSERT INTO cash_transactions (trans_no, trans_type, category, account_id, contra_account_id, amount, trans_date, description, created_by)
               VALUES ('BKK-" . date('Ymd') . "-001', 'KAS_KELUAR', 'BIAYA_OPERASIONAL', {$accKas}, {$accBebanInternet}, 350000, '{$today}', 'Pembayaran Wifi & Listrik Toko', 'admin')");
    
    $jnoExp = 'JU-' . date('Ymd') . '-0099';
    $db->exec("INSERT INTO journal_entries (journal_no, entry_date, reference_type, reference_id, description, created_by)
               VALUES ('{$jnoExp}', '{$today}', 'KAS_KELUAR', 'BKK-" . date('Ymd') . "-001', 'Beban Listrik & Internet Toko', 'admin')");
    $jidExp = $db->lastInsertId();
    $stmtJItem->execute([$jidExp, $accBebanInternet, 350000, 0, 'Beban Listrik & Internet']);
    $stmtJItem->execute([$jidExp, $accKas, 0, 350000, 'Pengeluaran Kas Tunai']);

    // Log Activity
    $db->exec("INSERT INTO activity_logs (user_id, user_name, action, description, ip_address) 
               VALUES (1, 'Administrator Utama', 'INITIALIZATION', 'Inisialisasi sistem SmartAcc Hub & data awal berhasil', '127.0.0.1')");
}
