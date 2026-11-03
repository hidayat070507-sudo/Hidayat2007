<?php
/**
 * SmartAcc Hub - Database Connection Wrapper
 * Supports SQLite (Default out-of-the-box) and MySQL
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            try {
                if (DB_DRIVER === 'sqlite') {
                    $dsn = 'sqlite:' . SQLITE_FILE;
                    self::$instance = new PDO($dsn);
                    self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                    self::$instance->exec('PRAGMA foreign_keys = ON;');
                    self::$instance->exec('PRAGMA journal_mode = WAL;');
                } else {
                    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
                    self::$instance = new PDO($dsn, DB_USER, DB_PASS);
                    self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                }
            } catch (PDOException $e) {
                die('<div style="font-family:sans-serif;padding:30px;background:#fee2e2;color:#991b1b;border-radius:10px;margin:30px auto;max-width:600px;">
                    <h2 style="margin-top:0;">Koneksi Database Gagal</h2>
                    <p>' . htmlspecialchars($e->getMessage()) . '</p>
                    <p><small>Jika menggunakan SQLite, pastikan folder database dapat ditulis. Jika menggunakan MySQL, pastikan server MySQL sudah berjalan.</small></p>
                </div>');
            }
        }
        return self::$instance;
    }
}
