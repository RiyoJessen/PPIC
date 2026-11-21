# PPIC & AI FORECASTING
### PT DUNIA KIMIA JAYA GRESIK

Sistem terintegrasi untuk pengelolaan **Perencanaan Produksi (PPIC)**, **Kontrol Persediaan Bahan Baku (Inventory)**, dan **Pengadaan Cerdas (Purchasing)** yang didukung oleh analisis **AI Google Gemini**.

---

## 💾 Database Engine: MySQL

Aplikasi ini telah dikonfigurasi dan berjalan menggunakan **MySQL (XAMPP)**:
- **Host**: `127.0.0.1:3306`
- **Database**: `ppic`
- **User**: `root`
- **Password**: *(kosong)*
- Seluruh tabel dan relasi foreign key termigrasi otomatis di MySQL dan siap diisi dengan data aktual PT Dunia Kimia Jaya Gresik.

## 🚀 Cara Menjalankan Aplikasi

Buka terminal di folder proyek dan jalankan:
```bash
php artisan serve
```
Kemudian buka browser dan akses:
👉 **[http://127.0.0.1:8000](http://127.0.0.1:8000)**

---

## 🔄 Cara Memasukkan Data Aktual PT Dunia Kimia Jaya Gresik

### 1. Melalui Menu Pengaturan & Master Data di Website
1. Buka menu **Pengaturan AI & Data** (`/settings`) untuk mengimpor daftar bahan baku aktual secara massal via CSV (**Unduh Contoh Template CSV** → isi data aktual → **Impor Data Sekarang**).
2. Jika ingin mengosongkan seluruh data transaksi & master sebelum memasukkan data baru, klik tombol **"Kosongkan Seluruh Data (Clean Slate)"**.
3. Lengkapi data **Master Material & Update Stok** (`/master/materials`), **Supplier/Vendor** (`/master/suppliers`), **Finish Good / Produk** (`/master/products`), **Formula BOM** (`/master/boms`), serta **Harga Pasar & AI Forecasting** (`/ai/forecast`).

### 2. Format CSV Bahan Baku
Kolom file CSV:
```csv
code,name,unit,min_stock,safety_stock,reorder_point,lead_time_days,moq,unit_cost
BB-101,Nama Bahan Aktual,Kg,10000,15000,25000,5,5000,4500
```

---

## 🧠 Integrasi Google Gemini AI

Sistem dilengkapi dua mode cerdas:
1. **Mode Live (Gemini API):**
   Masukkan API Key Google Gemini Anda di file `.env` atau melalui halaman `/settings` (`GEMINI_API_KEY=AIzaSy...`). Sistem akan langsung memanggil model `gemini-1.5-flash` untuk menghasilkan analisis naratif, evaluasi tren, dan rekomendasi pembelian secara real-time.
2. **Mode Offline (Simulasi Heuristik Cerdas):**
   Jika API Key tidak diisi, sistem tetap berfungsi 100% menggunakan engine algoritma cerdas yang menirukan gaya analisis naratif Gemini secara realistis berdasarkan rumus operasional PPIC.

---

## 📁 Struktur Folder Proyek
```
ppic/
├── app/
│   ├── Config/          # Konfigurasi & pemrosesan .env
│   ├── Database/        # Driver PDO (SQLite & MySQL), Migrasi, dan Seeder
│   ├── Models/          # Entitas data
│   ├── Services/        # Logika stok, kalkulasi BOM, rumus Q, dan Gemini API
│   └── Controllers/     # Controller per modul
├── views/               # Tampilan antarmuka Blade-like responsive
│   ├── dashboard/       # Executive KPI & chart Chart.js
│   ├── inventory/       # Stok fisik, batch/lot, COA, mutasi stok
│   ├── production/      # Jadwal PPIC & kalkulator kebutuhan BOM
│   ├── purchasing/      # PR, approval Manager, PO resmi, penerimaan fisik
│   ├── ai/              # AI Forecasting & Gemini Advisor
│   ├── master/          # Master Bahan, Produk Jadi, BOM, Supplier
│   ├── reports/         # Rekapitulasi & audit trail aktivitas
│   └── settings/        # Impor data aktual & konfigurasi AI
├── public/              # Document root web (CSS, JS, index.php)
├── database/            # File database SQLite (ppic.sqlite)
├── .env                 # Konfigurasi database & Google Gemini
├── artisan              # Laravel CLI tool
└── composer.json        # Dependensi & konfigurasi Laravel 13
```
