# SIMRS RS Permata Medika — Kelompok 7

Prototipe Sistem Informasi Manajemen Rumah Sakit (SIMRS) untuk RS Permata Medika, dibagi menjadi 3 modul. Setiap modul punya Login dan RBAC (Role-Based Access Control) sendiri, memakai daftar akun dan role yang sama.

| No | Modul | Folder | Isi |
| --- | --- | --- | --- |
| 1 | Authentication & Management | [`authentication/`](authentication/index.html) | Login, password sementara wajib diganti, RBAC (menu sesuai role, akses lain → 403), User Management, matriks hak akses, Akun Saya, audit trail |
| 2 | Billing & Insurance | [`billing/`](billing/index.html) | Daftar tagihan, tambah layanan (cegah duplikat), invoice, pembayaran self-pay, kuitansi, verifikasi penjamin BPJS/asuransi, pengajuan klaim, laporan billing |
| 3 | Inventory & Warehouse | [`warehouse/`](warehouse/index.html) | Stok & kartu stok, permintaan barang dari unit, penerimaan PO, pengeluaran/mutasi (tolak jika stok kurang), stok minimum/expiry, stock opname + approval, laporan inventory |

## Anggota

| Nama | NIM | Modul |
| --- | --- | --- |
| Victoria Darius Chong | 01071230150 | |
| | | |
| | | |

## Cara menjalankan

- Buka `index.html`, lalu pilih modul. Bisa juga langsung membuka `authentication/index.html`, `billing/index.html`, atau `warehouse/index.html`.
- Tidak perlu install apa pun; cukup browser (Chrome/Edge). Berjalan juga di HP.
- Jika GitHub Pages diaktifkan, aplikasi bisa dibuka lewat `https://<username>.github.io/<nama-repo>/`.

## Akun demo

Password awal semua akun: `simrs123`. Di halaman login, ketuk nama akun di daftar **Akun demo** untuk mengisi otomatis.

| Username | Role | Authentication | Billing | Warehouse |
| --- | --- | --- | --- | --- |
| andi.pratama | Admin SIMRS / IT | User Management, RBAC, Audit Trail | Ditolak | Ditolak |
| agus.mulyadi | Supervisor / Admin SIMRS | User Management (tanpa tambah), RBAC, Audit Trail | Laporan Billing | Ditolak |
| maria.santos | Petugas Kasir / Billing | Akun Saya | Tagihan, Pembayaran, Kuitansi | Ditolak |
| rudi.hartono | Verifikator Penjamin / Asuransi | Akun Saya | Verifikasi Penjamin, Klaim | Ditolak |
| neri.kwang | Apoteker | Akun Saya | Ditolak | Permintaan Barang, Stok |
| yohana.tan | Petugas Gudang / Logistik | Akun Saya | Ditolak | Penerimaan, Pengeluaran, Stock Opname |
| kevin.susanto | Supervisor / Kepala Instalasi | Akun Saya | Ditolak | Approve Opname, Laporan Inventory |
| dina.wijaya | Petugas Gudang (nonaktif) | Ditolak | Ditolak | Ditolak |

## Cara kerja RBAC

- Setiap role punya daftar hak akses (`PERMISSIONS` di `src/apps/<modul>/config.js`).
- Sidebar dibangun dari daftar menu; menu yang hak aksesnya tidak dimiliki role **tidak ditampilkan**.
- Setiap halaman dicek saat dibuka (route guard). Jika role tidak berhak, tampil **403 — Akses ditolak** dan tercatat di audit trail.
- Role yang sama sekali tidak punya hak di sebuah modul ditolak sejak login (contoh: Kasir login ke Warehouse).
- Tombol aksi juga dicek, misalnya *Tambah Pengguna* hanya untuk Admin IT dan *Approve Adjustment* hanya untuk Kepala Instalasi.

## Struktur repositori

```
├── index.html                  halaman pilih modul
├── authentication/index.html   Modul 1 (siap pakai, 1 file)
├── billing/index.html          Modul 2 (siap pakai, 1 file)
├── warehouse/index.html        Modul 3 (siap pakai, 1 file)
└── src/                        kode sumber per file (opsional)
    ├── shared/                 dipakai ketiga modul
    │   ├── base.js             role, unit, akun bersama, ikon
    │   ├── core.js             penyimpanan data, sesi, Auth/RBAC, router, modal & toast
    │   ├── login.js            halaman login
    │   ├── shell.js            layout, sidebar dinamis, route guard 403, logout
    │   └── style.css           tampilan (laptop & HP)
    ├── apps/auth/              config.js (hak akses, menu, data awal) + pages.js (halaman)
    ├── apps/billing/           config.js + pages.js
    ├── apps/inventory/         config.js + pages.js (modul Warehouse)
    └── build.py                gabungkan src/ menjadi 3 file index.html
```

File `index.html` di tiap folder modul sudah berisi seluruh kode modul itu (gabungan `src/`), jadi bisa langsung dijalankan. Jika folder `src/` disertakan dan kodenya diubah, jalankan `python build.py` dari folder `src` untuk membuat ulang ketiga file tersebut.

## Catatan

- Teknologi: HTML, CSS, dan JavaScript murni (tanpa framework dan tanpa server).
- Data pasien, tagihan, dan barang adalah data dummy (hard-coded). Perubahan disimpan di browser (localStorage) dan bisa dikembalikan lewat **Reset data demo** di halaman login.
- Password disimpan sebagai teks biasa khusus untuk demo; pada implementasi dengan backend, password harus di-hash dan data disimpan di database.
