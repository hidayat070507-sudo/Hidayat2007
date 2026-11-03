-- ==============================================================
-- SmartAcc Hub - MySQL Database Schema
-- Aplikasi Akuntansi & Manajemen Top Up Game Terpadu
-- ==============================================================

CREATE DATABASE IF NOT EXISTS `smartacc_hub` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `smartacc_hub`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'kasir', 'keuangan') NOT NULL DEFAULT 'kasir',
  `email` VARCHAR(100),
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Settings Table
CREATE TABLE IF NOT EXISTS `settings` (
  `key_name` VARCHAR(50) PRIMARY KEY,
  `value_data` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Activity Logs Table
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `user_name` VARCHAR(100),
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `ip_address` VARCHAR(50),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Accounts Table (COA)
CREATE TABLE IF NOT EXISTS `accounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(20) UNIQUE NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `type` VARCHAR(30) NOT NULL,
  `normal_balance` ENUM('DEBIT', 'KREDIT') NOT NULL DEFAULT 'DEBIT',
  `is_cash_bank` TINYINT(1) NOT NULL DEFAULT 0,
  `description` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Journal Entries Table
CREATE TABLE IF NOT EXISTS `journal_entries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `journal_no` VARCHAR(50) UNIQUE NOT NULL,
  `entry_date` DATE NOT NULL,
  `reference_type` VARCHAR(50) DEFAULT 'MANUAL',
  `reference_id` VARCHAR(50),
  `description` TEXT NOT NULL,
  `created_by` VARCHAR(100),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Journal Items Table
CREATE TABLE IF NOT EXISTS `journal_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `journal_id` INT NOT NULL,
  `account_id` INT NOT NULL,
  `debit` DECIMAL(15,2) DEFAULT 0.00,
  `credit` DECIMAL(15,2) DEFAULT 0.00,
  `memo` VARCHAR(255),
  FOREIGN KEY (`journal_id`) REFERENCES `journal_entries`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Game Categories
CREATE TABLE IF NOT EXISTS `game_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(30) UNIQUE NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `publisher` VARCHAR(100),
  `icon` VARCHAR(100),
  `status` VARCHAR(20) DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Game Products
CREATE TABLE IF NOT EXISTS `game_products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `item_name` VARCHAR(150) NOT NULL,
  `nominal` VARCHAR(100) NOT NULL,
  `unit` VARCHAR(50) DEFAULT 'Diamond',
  `modal_price` DECIMAL(15,2) NOT NULL,
  `sell_price` DECIMAL(15,2) NOT NULL,
  `status` VARCHAR(20) DEFAULT 'active',
  FOREIGN KEY (`category_id`) REFERENCES `game_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Customers
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30),
  `email` VARCHAR(100),
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Suppliers
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30),
  `email` VARCHAR(100),
  `balance` DECIMAL(15,2) DEFAULT 0.00,
  `status` VARCHAR(20) DEFAULT 'active',
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Top Up Orders
CREATE TABLE IF NOT EXISTS `topup_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(50) UNIQUE NOT NULL,
  `customer_id` INT,
  `customer_name` VARCHAR(100) NOT NULL,
  `category_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `game_name` VARCHAR(100) NOT NULL,
  `item_name` VARCHAR(150) NOT NULL,
  `user_id_game` VARCHAR(100) NOT NULL,
  `server_id_game` VARCHAR(100),
  `modal_price` DECIMAL(15,2) NOT NULL,
  `sell_price` DECIMAL(15,2) NOT NULL,
  `profit` DECIMAL(15,2) NOT NULL,
  `payment_method` VARCHAR(50) DEFAULT 'KAS',
  `payment_status` VARCHAR(30) DEFAULT 'LUNAS',
  `order_status` ENUM('PENDING', 'DIPROSES', 'BERHASIL', 'GAGAL') DEFAULT 'BERHASIL',
  `supplier_id` INT,
  `notes` TEXT,
  `created_by` VARCHAR(100),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Cash Transactions
CREATE TABLE IF NOT EXISTS `cash_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `trans_no` VARCHAR(50) UNIQUE NOT NULL,
  `trans_type` ENUM('KAS_MASUK', 'KAS_KELUAR') NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `account_id` INT NOT NULL,
  `contra_account_id` INT NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `trans_date` DATE NOT NULL,
  `description` TEXT,
  `created_by` VARCHAR(100),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Supplier Deposits
CREATE TABLE IF NOT EXISTS `supplier_deposits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `deposit_no` VARCHAR(50) UNIQUE NOT NULL,
  `supplier_id` INT NOT NULL,
  `account_id` INT NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `admin_fee` DECIMAL(15,2) DEFAULT 0.00,
  `total_deducted` DECIMAL(15,2) NOT NULL,
  `deposit_date` DATE NOT NULL,
  `status` VARCHAR(20) DEFAULT 'BERHASIL',
  `notes` TEXT,
  `created_by` VARCHAR(100),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. Transfers
CREATE TABLE IF NOT EXISTS `transfers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transfer_no` VARCHAR(50) UNIQUE NOT NULL,
  `from_account_id` INT NOT NULL,
  `to_account_id` INT NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `fee` DECIMAL(15,2) DEFAULT 0.00,
  `transfer_date` DATE NOT NULL,
  `notes` TEXT,
  `created_by` VARCHAR(100),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. Debts & Receivables
CREATE TABLE IF NOT EXISTS `debts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `debt_no` VARCHAR(50) UNIQUE NOT NULL,
  `type` ENUM('UTANG', 'PIUTANG') NOT NULL,
  `contact_type` VARCHAR(20) NOT NULL,
  `contact_name` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `paid_amount` DECIMAL(15,2) DEFAULT 0.00,
  `due_date` DATE,
  `status` VARCHAR(20) DEFAULT 'BELUM_LUNAS',
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
