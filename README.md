# TrademapScraper

Aplikasi web yang dibuat dengan Laravel untuk mengikis (scrape), memproses, dan memvisualisasikan data perdagangan dari [Trademap](https://www.trademap.org/).

## Kemampuan Aplikasi

Aplikasi ini sedang dalam tahap pengembangan menengah dan memiliki beberapa kemampuan utama:

### 1. Scraper Data Trademap
- **Scraper Otomatis**: Terdapat command `php artisan scrape:trademap-data` untuk mengikis data langsung dari situs Trademap.
- **Logika Scraper**: Logika scraper utama berada di `app/Services/Scrapers/TrademapScraper.php`.

### 2. Dashboard Visualisasi Data
- **Tampilan Data**: Dashboard sederhana untuk menampilkan data perdagangan yang telah diproses. Dapat diakses melalui route `/dashboard/trade-data`.
- **Controller**: Dikelola oleh `TradeDashboardController.php`.
- **Views**: Menggunakan komponen Blade seperti `trade-data.blade.php`.

### 3. Impor Data
- **Impor CSV**: Memiliki fungsionalitas untuk mengimpor data dari file CSV melalui antarmuka web.
- **Proses Latar Belakang**: Impor data CSV diproses sebagai _background job_ menggunakan `ProcessCsvImportJob.php` untuk menangani file besar tanpa memblokir _request_ utama.

### 4. API
- **Trade Ticker**: Menyediakan endpoint API di `/api/trade-ticker` untuk menyajikan data perdagangan dalam format JSON, cocok untuk integrasi dengan _frontend framework_ atau aplikasi lain.

### 5. Manajemen Data
- **Model Eloquent**: Menggunakan model `TabelPerdagangan` dan `TbTrade` untuk berinteraksi dengan database.
- **Migrasi Database**: Skema database untuk tabel perdagangan (`tb_trade`) telah didefinisikan dalam file migrasi.

### 6. Pemicu Job Via Web
- **Antarmuka Web**: Kemampuan untuk memicu (dispatch) job di latar belakang (background tasks) seperti scraping data dari antarmuka web, tanpa perlu akses langsung ke terminal.
- **Flow**: Tombol klik → Controller → Job Dispatch → Queue → Queue Worker → Eksekusi Job.

### 7. Command Tambahan
- **Setup Environment**: Termasuk command `php artisan app:setup-data-engineer-test` untuk mempersiapkan environment development atau test.

## Rencana Pengembangan Lanjutan

- **Standardisasi Bahasa**: Melakukan standardisasi ke Bahasa Indonesia di seluruh antarmuka pengguna.
- **Peningkatan Visualisasi**: Mengembangkan dashboard dengan grafik dan alat analisis yang lebih interaktif.
- **Optimasi Scraper**: Meningkatkan ketahanan dan efisiensi scraper, termasuk penambahan _feature_ dan _unit test_.

## Instalasi

1.  Clone repository ini.
2.  Jalankan `composer install`.
3.  Jalankan `npm install`.
4.  Salin `.env.example` menjadi `.env` dan konfigurasi koneksi database Anda.
5.  Jalankan `php artisan key:generate`.
6.  Jalankan `php artisan migrate` untuk membuat tabel-tabel database.
7.  Jalankan `npm run dev` untuk _assets bundling_.
8.  Jalankan `php artisan serve` untuk memulai _development server_.
