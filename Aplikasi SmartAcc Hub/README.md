# SmartAcc Hub 🎮💼
**Sistem Akuntansi & Manajemen Top Up Game Terpadu**  
*Dibangun murni dengan PHP Native, SQLite / MySQL, dan Modern Responsive Design.*

---

## 🌟 Highlight Fitur Utama

Aplikasi **SmartAcc Hub** dirancang khusus untuk toko digital, konter, agen voucher, dan bisnis top up game yang membutuhkan pencatatan operasional sekaligus pembukuan akuntansi terstandar (*Double-Entry Bookkeeping*).

1. **Fitur Ganti Warna Tema (Hitam & Putih / Dark & Light Mode)**:
   - Dilengkapi tombol toggle cepat di navigasi atas (Mode Gelap 🌙 & Mode Terang ☀️).
   - Tersimpan di `localStorage` dan Cookie sehingga otomatis persisten di seluruh halaman tanpa *flicker*.
2. **Otomatisasi Jurnal Akuntansi**:
   - Setiap transaksi top up berstatus **Berhasil** otomatis mencatat jurnal debit & kredit (Kas/Bank, Penjualan Game, Beban Pokok/HPP, dan Saldo Supplier).
3. **Multi-Database Support**:
   - **SQLite** (Bawaan otomatis, langsung jalan tanpa perlu instalasi database).
   - **MySQL** (Tersedia file skema `database/database.sql` jika ingin diunggah ke XAMPP / phpMyAdmin / cPanel).
4. **Keamanan & Role-Based Access Control (RBAC)**:
   - Password terenkripsi dengan `password_hash()` dan `password_verify()`.
   - Hak akses berjenjang: **Admin**, **Kasir**, dan **Bagian Keuangan**.

---

## 🚀 Cara Menjalankan Aplikasi

### Cara 1: Menggunakan File Batch (1-Klik di Windows)
Cukup klik dua kali pada file:
```
run_app.bat
```
Aplikasi otomatis menjalankan server lokal dan membuka browser di `http://localhost:8000`.

### Cara 2: Melalui Terminal / Command Prompt
Buka folder proyek ini di terminal, lalu jalankan:
```bash
php -S localhost:8000
```
Buka browser dan akses alamat:
👉 **[http://localhost:8000](http://localhost:8000)**

---

## 🔑 Akun Demo Login

Tersedia tombol demo cepat di halaman login untuk mengisi data secara otomatis:

| Role / Peran | Username | Password | Hak Akses |
|---|---|---|---|
| **Administrator** | `admin` | `admin123` | Akses penuh seluruh modul & pengaturan |
| **Kasir** | `kasir` | `kasir123` | Top up game, transaksi kasir, data pelanggan |
| **Bagian Keuangan** | `keuangan` | `keuangan123` | Kas masuk/keluar, jurnal, buku besar, laporan keuangan |

---

## 📂 Struktur Modul & Rincian Fitur

### 1. Dashboard
- **Ringkasan Finansial Real-Time**:
  - Total Saldo Kas & Bank fisik terakumulasi.
  - Total Omzet Penjualan game.
  - Total Modal Supplier (HPP) & Total Keuntungan Bersih.
  - Jumlah transaksi & laba hari ini.
- **Grafik Pemasukan & Pengeluaran**:
  - Visualisasi grafik Chart.js 7 hari terakhir yang otomatis menyesuaikan warna tema Gelap/Terang.
- **Monitoring Saldo Server Supplier**:
  - Peringatan dini jika saldo supplier menipis (< Rp 1.000.000).
- **Pesanan Terbaru & Notifikasi Pending**.

### 2. Manajemen Top Up Game
- **Daftar Game Populer**: Mobile Legends, Free Fire, PUBG Mobile, Genshin Impact, Valorant, dll.
- **Katalog Diamond & Item**: Pengaturan harga modal supplier dan harga jual toko.
- **Form Pesanan Cepat**:
  - Pilihan game visual dengan kartu ikon.
  - Perhitungan margin keuntungan langsung di layar (*live profit preview*).
  - Input User ID Game & Server/Zone ID pelanggan.
  - Status pesanan: `Pending`, `Diproses`, `Berhasil`, dan `Gagal`.
  - Cetak struk pembelian / nota digital pelanggan.

### 3. Manajemen Keuangan
- **Kas Masuk & Kas Keluar**:
  - Catat pemasukan lain-lain, modal masuk, dan beban operasional (listrik, internet, sewa, gaji, konsumsi).
  - Otomatis membuat jurnal kas masuk (BKM) dan kas keluar (BKK).
- **Deposit Saldo Supplier**:
  - Catat pengisian saldo server voucher (Digiflazz, Tokovoucher, dll.) via Kas/Bank.
  - Mendukung pencatatan biaya admin transfer.
- **Transfer Antar Rekening**:
  - Mutasi uang dari Kas Toko ke Bank BCA / Bank Mandiri.
- **Rekonsiliasi Kas & Bank**:
  - Pencocokan saldo sistem dengan hitungan fisik / rekening koran riil.
  - Fitur penyesuaian selisih otomatis ke jurnal.

### 4. Modul Akuntansi (Double-Entry Bookkeeping)
- **Chart of Accounts (COA)**: Bagan akun berstruktur standar (Aset, Kewajiban, Ekuitas, Pendapatan, HPP, Beban Operasional).
- **Jurnal Umum**: Menampilkan debit dan kredit berpasangan dengan validasi keseimbangan (*balance*).
- **Input Jurnal Manual**: Form dinamis dengan pengecekan saldo seimbang secara instan.
- **Buku Besar (General Ledger)**: Menampilkan mutasi transaksi per akun lengkap dengan *running balance* (saldo berjalan).
- **Neraca Saldo (Trial Balance)**: Memverifikasi keselarasan total debit dan kredit pada periode tertentu.
- **Jurnal Penyesuaian**: Pencatatan penyesuaian akhir periode (penyusutan aset, akrual, dll.).

### 5. Laporan Keuangan
- **Laporan Laba Rugi**: Pendapatan Usaha - HPP = Laba Kotor, dikurangi Beban Operasional = Laba Bersih.
- **Laporan Posisi Keuangan (Neraca)**: Menampilkan posisi Aset = Kewajiban + Ekuitas (*Balanced*).
- **Laporan Arus Kas**: Aliran kas masuk dan keluar dari aktivitas operasional dan pendanaan.
- **Laporan Penjualan per Game**: Analisis kontribusi omzet dan profit per judul game dilengkapi diagram batang.
- **Laporan Keuntungan per Transaksi**: Rincian margin nominal dan persentase (%) tiap faktur penjualan.
- **Ekspor Data**: Dukungan cetak rapi (*Print view*) dan ekspor ke **CSV / Excel**.

### 6. Manajemen Pelanggan dan Supplier
- **Data Pelanggan**:
  - Nama, nomor WhatsApp (dengan tautan *chat* langsung `wa.me`), email, dan catatan.
  - Riwayat akumulasi transaksi dan total belanja.
- **Data Supplier**:
  - Nama penyedia saldo, kontak API, riwayat deposit, dan pemantauan sisa saldo.
- **Catatan Utang & Piutang**:
  - Pemantauan tagihan belum lunas dari pelanggan atau ke supplier.
  - Form pelunasan cepat yang langsung memperbarui kas/bank dan jurnal akuntansi.

### 7. Pengaturan dan Keamanan
- **Profil Usaha**: Nama toko, slogan, nomor telepon, alamat, dan mata uang.
- **Manajemen Pengguna**: Tambah dan atur pengguna dengan role Admin, Kasir, atau Bagian Keuangan.
- **Log Aktivitas (Audit Trail)**: Catatan riwayat login, transaksi, dan perubahan data beserta alamat IP.
- **Backup & Pemulihan Database**:
  - Unduh file `.sqlite` untuk cadangan data.
  - Unduh skema MySQL `.sql`.
  - Unggah dan pulihkan database cadangan dengan aman.

---

## 📁 Struktur Berkas Proyek

```
d:/Aplikasi SmartAcc Hub/
├── index.php                 # Routing utama & controller
├── run_app.bat               # File runner 1-klik di Windows
├── config/
│   ├── config.php            # Konfigurasi sistem & konstanta
│   ├── database.php          # Koneksi PDO (SQLite & MySQL)
│   └── schema.php            # Migrasi tabel & data awal (seeder)
├── includes/
│   ├── auth.php              # Otentikasi sesi, RBAC & CSRF
│   ├── functions.php         # Format rupiah, jurnal helper, status badge
│   ├── header.php            # Navbar, theme toggle, script CDN
│   ├── sidebar.php           # Navigasi menu samping sesuai role
│   └── footer.php            # Penutup template & script
├── assets/
│   ├── css/
│   │   └── style.css         # Desain modern, CSS Variables Dark/Light
│   └── js/
│       └── app.js            # Tema switcher, modal logic, dynamic calc
├── modules/
│   ├── auth/                 # Login & Logout
│   ├── dashboard/            # Dashboard keuangan & grafik Chart.js
│   ├── topup/                # Pesanan topup & produk diamond
│   ├── keuangan/             # Kas masuk/keluar, deposit, transfer, rekonsiliasi
│   ├── akuntansi/            # COA, jurnal umum, buku besar, neraca saldo, penyesuaian
│   ├── laporan/              # Laba rugi, neraca, arus kas, per game, transaksi, ekspor
│   ├── kontak/               # Pelanggan, supplier, utang & piutang
│   └── pengaturan/           # Profil usaha, users, audit log, backup/restore
└── database/
    ├── smartacc.sqlite       # Database SQLite bawaan
    └── database.sql          # File ekspor untuk MySQL / phpMyAdmin
```

---
*SmartAcc Hub &copy; 2026. Dibuat dengan standar performa tinggi dan tampilan modern.*
