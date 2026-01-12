# MANUAL BOOK (PETUNJUK PENGGUNAAN)
## PROGRAM KOMPUTER: HARMONIDATA

<br>

**[SISIPKAN GAMBAR LOGO APLIKASI DI SINI]**

<br>

**JUDUL CIPTAAN:**
### HARMONIDATA: PLATFORM ANALISIS STRUKTURAL DAN OTOMASI DATA PERDAGANGAN INTERNASIONAL

<br>

**JENIS CIPTAAN:**
Program Komputer (Aplikasi Web)

<br>

**PENCIPTA:**
1. **[NAMA DOSEN PEMBIMBING/KETUA]** - NIDN: [NOMOR]
2. **[NAMA MAHASISWA 1]** - NPM: [NOMOR]
3. **[NAMA MAHASISWA 2]** - NPM: [NOMOR]

<br>

**INSTITUSI:**
[NAMA UNIVERSITAS / FAKULTAS / PROGRAM STUDI]
[TAHUN 2026]

---

<div style="page-break-after: always;"></div>

## DAFTAR ISI

1. **BAB I: PENDAHULUAN**
   - 1.1 Latar Belakang
   - 1.2 Tujuan dan Fungsi Strategis
   - 1.3 Arsitektur Sistem
2. **BAB II: INSTALASI DAN KONFIGURASI**
   - 2.1 Persyaratan Sistem
   - 2.2 Langkah-Langkah Instalasi
3. **BAB III: PETUNJUK PENGGUNAAN FITUR**
   - 3.1 Navigasi Utama (Sidebar Explorer)
   - 3.2 Dashboard Analisis Struktural & Interaktif Treemap
   - 3.3 Leaderboard Top Sektor & Market Share
   - 3.4 Analisis Tren 5 Tahun (Sparkline Data)
   - 3.5 Manajemen Scraping Data Otomatis (Background Jobs)
   - 3.6 Manajemen Dataset (Import/Export CSV)
4. **BAB IV: ANALISIS LOGIKA PROGRAM (CORE CODE)**
   - 4.1 Mesin Otomasi (Scraper Engine)
   - 4.2 Pemrosesan Data & Agregasi Tren
   - 4.3 Logika Background Processing

---

<div style="page-break-after: always;"></div>

## BAB I: PENDAHULUAN

### 1.1 Latar Belakang
**HarmoniData** adalah aplikasi berbasis web yang dirancang untuk melakukan ekstraksi, pengolahan, dan visualisasi data perdagangan internasional secara otomatis. Aplikasi ini menjawab tantangan sulitnya menganalisis postur perdagangan yang memiliki ribuan kategori produk (HS Code) melalui visualisasi yang intuitif dan data yang selalu diperbarui.

### 1.2 Tujuan dan Fungsi Strategis
Fungsi utama aplikasi ditekankan pada **Analisis Struktural**, yaitu kemampuan untuk memahami komposisi ekonomi sebuah negara berdasarkan porsi impor/ekspor per kategori produk. Sistem ini mengotomatiskan pengambilan data dari penyedia data global (ITC Trademap) dan menyajikannya dalam format yang siap dianalisis untuk pengambilan keputusan strategis.

### 1.3 Arsitektur Sistem
Aplikasi ini dikembangkan menggunakan stack teknologi:
- **Backend:** Laravel Framework (PHP)
- **Database:** MySQL
- **Scraper:** Node.js/Puppeteer (Integrated)
- **Visualisasi:** ApexCharts (Data-Driven Graphics)

---

<div style="page-break-after: always;"></div>

## BAB II: INSTALASI DAN KONFIGURASI

### 2.1 Persyaratan Sistem
- PHP >= 8.2
- MySQL >= 8.0
- Node.js & NPM
- Composer

### 2.2 Langkah-Langkah Instalasi
1. Ekstrak *source code* ke direktori server.
2. Jalankan perintah instalasi dependensi:
   ```bash
   composer install
   npm install
   ```
3. Konfigurasi file `.env` untuk koneksi basis data.
4. Jalankan migrasi basis data:
   ```bash
   php artisan migrate
   ```

**[SISIPKAN TANGKAPAN LAYAR PROSES INSTALASI DI SINI]**

---

<div style="page-break-after: always;"></div>

## BAB III: PETUNJUK PENGGUNAAN FITUR

### 3.1 Navigasi Utama (Sidebar Explorer)
Antarmuka HarmoniData menggunakan sidebar kategori yang padat informasi (*data-dense*) untuk memudahkan akses:
- **EXPLORER:** Akses Dashboard utama.
- **DATA MANAGEMENT:** Pengolahan file dan penyegaran data ticker.
- **SYSTEM:** Pengaturan teknis dan antrean proses.

**[SISIPKAN TANGKAPAN LAYAR SIDEBAR DI SINI]**

### 3.2 Dashboard Analisis Struktural & Interaktif Treemap
Dashboard utama menyajikan visualisasi **Treemap** yang mencerminkan porsi pasar produk.
- **Interaktivitas:** Klik pada salah satu blok produk (HS2) untuk melakukan *drill-down* ke tingkat yang lebih detail (HS4).
- **Tombol Back:** Gunakan tombol panah di pojok kanan kartu untuk kembali ke level sebelumnya.

**[SISIPKAN TANGKAPAN LAYAR TREEMAP DI SINI]**

### 3.3 Leaderboard Top Sektor & Market Share
Di samping grafik Treemap, terdapat panel **Top Sektor** yang berfungsi sebagai papan peringkat otomatis. Panel ini menampilkan:
- Kode HS dan Nilai Transaksi (dalam Miliar USD).
- Persentase pangsa pasar (*market share*) terhadap total impor.

**[SISIPKAN TANGKAPAN LAYAR TOP SEKTOR DI SINI]**

### 3.4 Analisis Tren 5 Tahun (Sparkline Data)
Tabel data di bagian bawah menyediakan rincian angka setiap tahunnya. Fitur unggulan di sini adalah kolom **Trend (5Y)** yang menggunakan grafik garis mini (*sparkline*) untuk menunjukkan fluktuasi data selama 5 tahun terakhir tanpa perlu membuka halaman baru.

**[SISIPKAN TANGKAPAN LAYAR TABEL DAN SPARKLINE DI SINI]**

### 3.5 Manajemen Scraping Data Otomatis (Background Jobs)
Menu **Jobs** memungkinkan pengguna memicu pengambilan data baru secara otomatis. Proses ini berjalan di latar belakang sehingga pengguna dapat tetap menggunakan aplikasi sambil menunggu data diperbarui.

**[SISIPKAN TANGKAPAN LAYAR HALAMAN JOBS DI SINI]**

---

<div style="page-break-after: always;"></div>

## BAB IV: ANALISIS LOGIKA PROGRAM (CORE CODE)

### 4.1 Mesin Otomasi (Scraper Engine)
Berikut adalah potongan kode unik yang menangani simulasi interaksi browser untuk mengambil data perdagangan:

```php
// Lokasi: app/Services/Scrapers/TrademapScraper.php
    protected function executePuppeteerScraping(string $url): ?string
    {
        try {
            $puppeteerScript = base_path('storage/app/fixed_trademap_scraper.cjs');
            
            if (!file_exists($puppeteerScript)) {
                Log::error("Script not found: {$puppeteerScript}");
                return null;
            }
            
            $command = "node " . escapeshellarg($puppeteerScript) . " " . escapeshellarg($url) . " 2>&1";
            
            Log::info("Executing: {$command}");
            
            $output = [];
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                Log::error("Puppeteer failed with code: {$returnCode}");
                return null;
            }
            
            // ... (Kode pemrosesan output JSON)
            
            return $jsonData;
            
        } catch (Exception $e) {
            Log::error("Puppeteer execution failed: " . $e->getMessage());
            return null;
        }
    }
```

### 4.2 Pemrosesan Data & Agregasi Tren
Logika untuk menghitung tren 5 tahun dan market share secara dinamis:

```php
// Lokasi: app/Http/Controllers/TradeDashboardController.php
    /**
     * Get top trading sectors/products dynamic to the current view
     */
    private function getTopSectors(string $hsLevel = '2', ?string $searchPrefix = null)
    {
        $totalImports2024 = TbTrade::where('tahun', 2024)->sum('jumlah');

        $query = TbTrade::select([
            'kode_hs as sector_code',
            DB::raw('MAX(label) as sector_name'),
            DB::raw('SUM(jumlah) as total_value'),
            DB::raw('COUNT(*) as record_count')
        ])->where('tahun', 2024);

        // Filter by Level
        $level = (int) $hsLevel;
        $query->where(DB::raw("LENGTH(REPLACE(kode_hs, '.', ''))"), $level);

        // Filter by Prefix (Drill Down)
        if (!empty($searchPrefix)) {
            $query->where('kode_hs', 'LIKE', $searchPrefix . '%');
        }

        $topSectors = $query->groupBy('kode_hs')
            ->orderByDesc('total_value')
            ->limit(10)
            ->get();

        // Add share_percentage to each sector
        return $topSectors->map(function ($sector) use ($totalImports2024) {
            $sector->share_percentage = ($sector->total_value / $totalImports2024) * 100;
            return $sector;
        });
    }
```

### 4.3 Logika Background Processing
Implementasi asinkron untuk menangani dataset skala besar:

```php
// Lokasi: app/Jobs/ScrapeTrademapDataJob.php
    public function handle(): void
    {
        $this->updateProgress('running', "Scraping started for product code: {$this->productCode}...");

        try {
            $scraper = new TrademapScraper();
            $result = $scraper->execute($this->productCode);

            if ($result['success']) {
                $this->updateProgress('completed', 'Scraping completed successfully.', $result);
                Log::info('ScrapeTrademapDataJob finished successfully.', $result);
            } else {
                $this->updateProgress('failed', $result['message'], $result);
                Log::error('ScrapeTrademapDataJob failed.', $result);
            }
        } catch (\Exception $e) {
            $this->updateProgress('failed', 'Job failed: ' . $e->getMessage());
            throw $e;
        }
    }
```

---
**2026**
