# SIMRS Basic Week 7 - RS Permata Medika

Prototype aplikasi SIMRS untuk target **Basic** Week 7. Fokus utama: login, RBAC, sidebar dinamis, dashboard/kerangka halaman untuk tiga modul: Reporting & Dashboard, Outpatient, dan Nursing.

## Teknologi
- PHP Native 8+
- MySQL / MariaDB
- HTML, CSS, JavaScript
- XAMPP dapat digunakan untuk menjalankan project secara lokal

## Cara Menjalankan di XAMPP
1. Salin folder `SIMRS_Basic_Week7` ke `C:\xampp\htdocs\`.
2. Jalankan Apache dan MySQL dari XAMPP Control Panel.
3. Buka phpMyAdmin: `http://localhost/phpmyadmin`.
4. Pilih menu Import, lalu import `database/simrs_basic.sql`.
5. Pastikan pengaturan database di `config/database.php` sesuai. Default XAMPP: user `root`, password kosong.
6. Buka `http://localhost/SIMRS_Basic_Week7/`.

## Akun Demo
Password semua akun: `password123`

| Username | Role | Akses |
|---|---|---|
| management | Hospital Management | Reporting & Dashboard, Finance |
| outpatient | Outpatient Staff | Queue & Data, Data Entry |
| nurse | Nurse | Patient & Care, Assessment |

## RBAC
RBAC diterapkan pada dua lapisan:
1. Sidebar hanya menampilkan menu yang sesuai role login.
2. Halaman memeriksa role di sisi server. User yang mencoba membuka URL modul lain akan menerima halaman 403.

## Halaman
- `reporting.php` - Reporting & Dashboard: layanan
- `finance.php` - Reporting & Dashboard: finance
- `outpatient_queue.php` - Outpatient: Queue & Data
- `outpatient_entry.php` - Outpatient: Data Entry
- `nursing_overview.php` - Nursing: Patient & Care Overview
- `nursing_assessment.php` - Nursing: Assessment & Care Entry

## Catatan Scope
Project ini mengikuti target **Basic**, bukan aplikasi SIMRS produksi. Data pada sistem merupakan data dummy untuk simulasi dan demonstrasi. Form Data Entry dan Nursing Assessment disimpan ke MySQL agar aplikasi tidak hanya berupa tampilan statis.
