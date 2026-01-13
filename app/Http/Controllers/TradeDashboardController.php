<?php

namespace App\Http\Controllers;

use App\Models\TbTrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Jobs\ProcessCsvImportJob;

class TradeDashboardController extends Controller
{
    /**
     * Display the trade data dashboard (Pustik style)
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search = $request->get('search', '');
        // Default to HS Level 2, and validate it's one of the allowed options.
        $hsLevel = in_array($request->get('hs_level'), ['2', '4', '6']) ? $request->get('hs_level') : '2';
        $searchPrefix = $request->get('search_prefix', '');
        
        // Smart Year Detection: Anchor to the latest data in the DB with actual values
        // We filter for years that have at least one record with non-zero value to avoid anchoring to empty future years
        $maxYearInDb = (int) TbTrade::where('jumlah', '>', 0)->max('tahun');
        
        // Fallback to 2024 if DB is empty or max year is strangely low
        $targetYear = ($maxYearInDb > 2020) ? $maxYearInDb : 2024;
        
        // Dynamic Range: Only show years that actually have data within the last 5 years
        // This hides empty columns (like 2020/2021) if the scraper only found data for 2022-2024
        $availableYears = TbTrade::where('jumlah', '>', 0)
            ->whereBetween('tahun', [$targetYear - 4, $targetYear])
            ->distinct()
            ->pluck('tahun')
            ->sort()
            ->values()
            ->all();

        // If no data found (e.g. fresh install), default to the standard 5-year range ending in targetYear
        if (empty($availableYears)) {
            $years = range($targetYear - 4, $targetYear);
        } else {
            $years = $availableYears;
        }
        
        // Build Select Statement Dynamically
        $selects = [
            'kode_hs',
            DB::raw('MAX(label) as product_label')
        ];
        
        foreach ($years as $year) {
            $selects[] = DB::raw("SUM(CASE WHEN tahun = {$year} THEN jumlah ELSE 0 END) as value_{$year}");
        }
        
        $selects[] = DB::raw('SUM(jumlah) as total_value');

        // Get aggregated trade data by HS code with yearly breakdown
        $query = TbTrade::select($selects)->groupBy('kode_hs');
        
        // Apply search filter
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('kode_hs', 'LIKE', "%{$search}%")
                  ->orWhere('label', 'LIKE', "%{$search}%");
            });
        }

        // Apply prefix search for drill-down
        if (!empty($searchPrefix)) {
            $query->where('kode_hs', 'LIKE', $searchPrefix . '%');
        }
        
        // Always apply HS level filter
        $level = (int) $hsLevel;
        $query->where(DB::raw("LENGTH(REPLACE(kode_hs, '.', ''))"), $level);
        
        // Order by total value descending (show highest imports first)
        $query->orderByDesc('total_value');
        
        $tradeData = $query->paginate($perPage);
        
        // Get summary statistics for Pustik-style cards
        $summaryStats = $this->getSummaryStatistics($targetYear);
        
        // Get top sectors for additional insights (dynamic based on current level)
        $topSectors = $this->getTopSectors($hsLevel, $searchPrefix, $targetYear);
        $treemapData = $this->getTreemapData($hsLevel, $searchPrefix, $targetYear);
        
        return view('dashboard.trade-data', compact(
            'tradeData', 
            'summaryStats', 
            'topSectors',
            'treemapData',
            'search',
            'perPage',
            'hsLevel',
            'searchPrefix',
            'years',
            'targetYear' // Renaming for clarity, view can use $targetYear or $years array
        ));
    }
    
    /**
     * Show the page for dispatching jobs.
     */
    public function showJobsPage()
    {
        return view('dashboard.jobs');
    }

    /**
     * Get summary statistics for the dashboard
     */
    private function getSummaryStatistics(int $targetYear)
    {
        $totalRecords = TbTrade::count();
        // Use the target year (current year) for total value
        $totalValueYear = TbTrade::where('tahun', $targetYear)->sum('jumlah');
        
        // Fallback: If current year has 0 data, try previous year
        if ($totalValueYear == 0) {
            $targetYear--;
            $totalValueYear = TbTrade::where('tahun', $targetYear)->sum('jumlah');
        }

        $totalHsCodes = TbTrade::distinct('kode_hs')->count();
        $lastUpdate = TbTrade::latest('scraped_at')->first()?->scraped_at;
        
        return [
            'total_records' => $totalRecords,
            'total_value_year' => $totalValueYear,
            'display_year' => $targetYear,
            'total_hs_codes' => $totalHsCodes,
            'last_update' => $lastUpdate
        ];
    }

    /**
     * Get data for the treemap chart (top 20 products by value in current year)
     */
    private function getTreemapData(string $hsLevel, ?string $searchPrefix, int $year)
    {
        // Try current year, fallback to previous if empty
        $totalImports = TbTrade::where('tahun', $year)->sum('jumlah');
        if ($totalImports == 0) {
            $year--;
            $totalImports = TbTrade::where('tahun', $year)->sum('jumlah');
        }

        if ($totalImports == 0) {
            return collect(); // Return empty collection if total is zero
        }

        $query = TbTrade::select([
            'kode_hs as x',
            DB::raw('SUM(jumlah) as y'),
            DB::raw('MAX(label) as full_label')
        ])
        ->where('tahun', $year)
        ->groupBy('kode_hs');

        $level = (int) $hsLevel;
        $query->where(DB::raw("LENGTH(REPLACE(kode_hs, '.', ''))"), $level);

        if (!empty($searchPrefix)) {
            $query->where('kode_hs', 'LIKE', $searchPrefix . '%');
        }

        $topProducts = $query->orderByDesc('y')
            ->limit(20)
            ->get();

        // Add share_percentage to each product
        return $topProducts->map(function ($product) use ($totalImports) {
            $product->share_percentage = ($product->y / $totalImports) * 100;
            return $product;
        });
    }
    
    /**
     * Get top trading sectors/products dynamic to the current view
     */
    private function getTopSectors(string $hsLevel, ?string $searchPrefix, int $year)
    {
        // Try current year, fallback to previous if empty
        $totalImports = TbTrade::where('tahun', $year)->sum('jumlah');
        if ($totalImports == 0) {
            $year--;
            $totalImports = TbTrade::where('tahun', $year)->sum('jumlah');
        }

        if ($totalImports == 0) {
            return collect();
        }

        $query = TbTrade::select([
            'kode_hs as sector_code',
            DB::raw('MAX(label) as sector_name'),
            DB::raw('SUM(jumlah) as total_value'),
            DB::raw('COUNT(*) as record_count')
        ])
        ->where('tahun', $year);

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
        return $topSectors->map(function ($sector) use ($totalImports) {
            $sector->share_percentage = ($sector->total_value / $totalImports) * 100;
            return $sector;
        });
    }
    
    /**
     * Export trade data to CSV in TbTrade format (compatible with import)
     */
    public function export(Request $request)
    {
        $search = $request->get('search', '');
        
        // Get raw TbTrade data (not aggregated) for import compatibility
        $query = TbTrade::select([
            'negara',
            'kode_hs', 
            'label',
            'tahun',
            'jumlah',
            'satuan',
            'sumber_data'
        ]);
        
        // Apply search filter if provided
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('kode_hs', 'LIKE', "%{$search}%")
                ->orWhere('label', 'LIKE', "%{$search}%")
                ->orWhere('negara', 'LIKE', "%{$search}%");
            });
        }
        
        // Order by logical grouping
        $data = $query->orderBy('negara')
                    ->orderBy('kode_hs')
                    ->orderBy('tahun')
                    ->get();
        
        $filename = 'tb_trade_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];
        
        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // CSV headers - exactly matching import format for perfect cycle
            $headerRow = [
                'negara',
                'kode_hs', 
                'label',
                'tahun',
                'jumlah',
                'satuan',
                'sumber_data'
            ];
            fputcsv($file, $headerRow);
            
            // CSV data - clean and consistent formatting
            foreach ($data as $row) {
                $csvRow = [
                    trim($row->negara ?? ''),
                    trim($row->kode_hs ?? ''),
                    trim($row->label ?? ''),
                    intval($row->tahun ?? date('Y')),
                    number_format($row->jumlah, 2, '.', ''), // Consistent decimal format
                    trim($row->satuan ?? '-'),
                    trim($row->sumber_data ?? 'Trademap')
                ];
                fputcsv($file, $csvRow);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    /**
     * FIXED: Validate CSV files with more permissive MIME validation
     */
    public function validateCsv(Request $request)
    {
        // More permissive validation to handle browser download variations
        $request->validate([
            'csv_files.*' => [
                'required',
                'file',
                'max:102400', // 100MB per file
                function ($attribute, $value, $fail) {
                    $allowedExtensions = ['csv', 'txt'];
                    $extension = strtolower($value->getClientOriginalExtension());
                    
                    if (!in_array($extension, $allowedExtensions)) {
                        $fail("The {$attribute} must be a CSV or TXT file.");
                    }
                }
            ]
        ]);

        $validationResults = [];
        $totalEstimatedRecords = 0;

        foreach ($request->file('csv_files') as $file) {
            $validation = $this->validateSingleCsv($file);
            $validationResults[] = $validation;
            $totalEstimatedRecords += $validation['estimated_records'];
        }

        return response()->json([
            'success' => true,
            'files' => $validationResults,
            'total_files' => count($validationResults),
            'total_estimated_records' => $totalEstimatedRecords,
            'estimated_time' => $this->estimateProcessingTime($totalEstimatedRecords)
        ]);
    }

    /**
     * Show the CSV import page
     */
    public function showImport()
    {
        // Get some stats for the import page
        $stats = [
            'total_records' => TbTrade::count(),
            'last_import' => TbTrade::latest('scraped_at')->first()?->scraped_at,
            'total_countries' => TbTrade::distinct('negara')->count(),
            'total_hs_codes' => TbTrade::distinct('kode_hs')->count(),
        ];

        return view('dashboard.import-csv', compact('stats'));
    }

    /**
     * WORKING: Full CSV import with actual data processing
     */
    public function importCsv(Request $request)
    {
        try {
            Log::info('=== CSV IMPORT STARTED ===');
            
            // Basic validation
            if (!$request->hasFile('csv_files')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No files uploaded'
                ], 400);
            }

            $files = $request->file('csv_files');
            $chunkSize = intval($request->get('chunk_size', 1000));
            $validateData = $request->get('validate_data', true);
            
            Log::info('Processing settings:', [
                'files_count' => count($files),
                'chunk_size' => $chunkSize,
                'validate_data' => $validateData
            ]);

            $totalRecordsImported = 0;
            $totalErrors = 0;
            $processedFiles = 0;

            foreach ($files as $file) {
                Log::info("Processing file: " . $file->getClientOriginalName());
                
                $result = $this->processCSVFile($file, $chunkSize, $validateData);
                
                $totalRecordsImported += $result['records_imported'];
                $totalErrors += $result['errors'];
                $processedFiles++;
                
                Log::info("File processed:", $result);
            }

            Log::info('=== IMPORT COMPLETED ===', [
                'files_processed' => $processedFiles,
                'total_records_imported' => $totalRecordsImported,
                'total_errors' => $totalErrors
            ]);

            return response()->json([
                'success' => true,
                'message' => "Import completed successfully!",
                'job_id' => Str::uuid(),
                'processing_mode' => 'direct',
                'result' => [
                    'files_processed' => $processedFiles,
                    'records_imported' => $totalRecordsImported,
                    'errors' => $totalErrors
                ]
            ]);

        } catch (\Throwable $e) {
            Log::error('Import exception:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get import progress for AJAX tracking
     */
    public function getImportProgress($jobId)
    {
        $progress = Cache::get("csv_import_progress_{$jobId}", [
            'status' => 'not_found',
            'progress' => 0,
            'current_file' => 0,
            'total_files' => 0,
            'records_processed' => 0,
            'records_imported' => 0,
            'errors' => 0,
            'message' => 'Import job not found'
        ]);

        return response()->json($progress);
    }

    /**
     * ENHANCED: CSV validation with better error handling
     */
    private function validateSingleCsv($file)
    {
        $validation = [
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'size_formatted' => $this->formatBytes($file->getSize()),
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'estimated_records' => 0,
            'columns_found' => [],
            'sample_data' => []
        ];

        try {
            $handle = fopen($file->getRealPath(), 'r');
            
            if ($handle === false) {
                throw new \Exception('Cannot read file');
            }

            // Skip BOM if present
            $bom = fread($handle, 3);
            if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) {
                rewind($handle);
            }

            // Read first line (headers)
            $headers = fgetcsv($handle);
            if ($headers === false || empty($headers)) {
                throw new \Exception('Cannot read CSV headers or file is empty');
            }

            // Clean headers
            $headers = array_map('trim', $headers);
            $validation['columns_found'] = $headers;

            // Validate required columns for TbTrade
            $requiredColumns = ['negara', 'kode_hs', 'label', 'tahun', 'jumlah'];
            $missingColumns = array_diff($requiredColumns, $headers);
            
            if (!empty($missingColumns)) {
                $validation['errors'][] = 'Missing required columns: ' . implode(', ', $missingColumns);
                $validation['valid'] = false;
            }

            // Check optional columns
            $optionalColumns = ['satuan', 'sumber_data'];
            foreach ($optionalColumns as $col) {
                if (!in_array($col, $headers)) {
                    $validation['warnings'][] = "Optional column '{$col}' not found - will use default value";
                }
            }

            // Read sample data with enhanced validation
            $sampleCount = 0;
            $lineNumber = 1;
            while (($row = fgetcsv($handle)) !== false && $sampleCount < 3) {
                $lineNumber++;
                
                // Skip empty rows
                if (empty(array_filter($row, 'strlen'))) {
                    continue;
                }
                
                // Check column count
                if (count($row) !== count($headers)) {
                    $validation['warnings'][] = "Line {$lineNumber}: Column count mismatch (expected " . count($headers) . ", got " . count($row) . ")";
                    continue;
                }
                
                try {
                    $combinedData = array_combine($headers, $row);
                    if ($combinedData !== false) {
                        $validation['sample_data'][] = $combinedData;
                        $sampleCount++;
                    }
                } catch (\Exception $e) {
                    $validation['warnings'][] = "Line {$lineNumber}: Error combining data - " . $e->getMessage();
                }
            }

            // Estimate total records
            $validation['estimated_records'] = $this->estimateRecordsCount($file);

            fclose($handle);

        } catch (\Exception $e) {
            $validation['valid'] = false;
            $validation['errors'][] = $e->getMessage();
        }

        return $validation;
    }

    /**
     * Process a single CSV file and import to database
     */
    private function processCSVFile($file, $chunkSize, $validateData)
    {
        $handle = fopen($file->getRealPath(), 'r');
        
        if ($handle === false) {
            throw new \Exception("Cannot open file: " . $file->getClientOriginalName());
        }

        // Skip BOM if present
        $bom = fread($handle, 3);
        if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) {
            rewind($handle);
        }

        // Read headers
        $headers = fgetcsv($handle);
        if ($headers === false || empty($headers)) {
            fclose($handle);
            throw new \Exception("Cannot read CSV headers");
        }

        // Clean headers
        $headers = array_map('trim', $headers);
        $expectedColumnCount = count($headers);
        
        Log::info("CSV headers:", $headers);

        $recordsImported = 0;
        $errors = 0;
        $batch = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            
            // Skip empty rows
            if (empty(array_filter($row, 'strlen'))) {
                continue;
            }
            
            try {
                // Validate column count
                if (count($row) !== $expectedColumnCount) {
                    throw new \Exception("Column count mismatch on line {$lineNumber}");
                }
                
                $record = $this->mapCsvRowToTbTrade($headers, $row, $validateData, $lineNumber);
                if ($record) {
                    $batch[] = $record;
                }

                // Process batch when chunk size reached
                if (count($batch) >= $chunkSize) {
                    $imported = $this->insertBatch($batch);
                    $recordsImported += $imported;
                    $batch = [];
                    
                    Log::info("Processed batch: {$imported} records imported");
                }

            } catch (\Exception $e) {
                $errors++;
                Log::warning("Row error on line {$lineNumber}: " . $e->getMessage());
            }
        }

        // Process remaining records
        if (!empty($batch)) {
            $imported = $this->insertBatch($batch);
            $recordsImported += $imported;
            Log::info("Final batch: {$imported} records imported");
        }

        fclose($handle);

        return [
            'records_imported' => $recordsImported,
            'errors' => $errors
        ];
    }

    /**
     * Map CSV row to TbTrade model structure
     */
    private function mapCsvRowToTbTrade($headers, $row, $validateData, $lineNumber = null)
    {
        if (count($headers) !== count($row)) {
            throw new \Exception("Header/row count mismatch" . ($lineNumber ? " on line {$lineNumber}" : ""));
        }
        
        $data = array_combine($headers, $row);
        
        if ($data === false) {
            throw new \Exception("Failed to combine headers and row data");
        }
        
        $record = [
            'negara' => trim($data['negara'] ?? 'UNKNOWN'),
            'kode_hs' => trim($data['kode_hs'] ?? ''),
            'label' => trim($data['label'] ?? ''),
            'tahun' => intval($data['tahun'] ?? date('Y')),
            'jumlah' => floatval(str_replace(',', '', $data['jumlah'] ?? 0)),
            'satuan' => trim($data['satuan'] ?? '-'),
            'sumber_data' => trim($data['sumber_data'] ?? 'CSV Import'),
            'scraped_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ];

        // Basic validation if enabled
        if ($validateData) {
            if (empty($record['kode_hs']) || empty($record['label'])) {
                throw new \Exception('Missing required fields: kode_hs or label');
            }
        }

        return $record;
    }

    /**
     * Clean string values
     */
    private function cleanString($value)
    {
        return trim(strip_tags($value ?? ''));
    }

    /**
     * Parse year values safely
     */
    private function parseYear($value)
    {
        $year = intval(trim($value));
        
        // Handle 2-digit years
        if ($year >= 50 && $year <= 99) {
            $year += 1900;
        } elseif ($year >= 0 && $year <= 49) {
            $year += 2000;
        }
        
        return $year;
    }

    /**
     * FIXED: Enhanced numeric parsing
     */
    private function parseNumeric($value)
    {
        if (empty($value)) return 0.0;
        
        $cleaned = trim($value);
        
        // Remove common thousand separators but preserve decimal points
        $cleaned = preg_replace('/[,\s]/', '', $cleaned);
        
        // Handle negative values
        $isNegative = strpos($cleaned, '-') !== false;
        $cleaned = str_replace('-', '', $cleaned);
        
        $result = (float) $cleaned; // Explicit float cast
        
        return $isNegative ? -$result : $result;
    }

    /**
     * FIXED: Enhanced record validation
     */
    private function validateRecord($record, $lineNumber = null)
    {
        $errors = [];
        
        if (empty($record['kode_hs'])) {
            $errors[] = 'Missing required field: kode_hs';
        }
        
        if (empty($record['label'])) {
            $errors[] = 'Missing required field: label';
        }
        
        if ($record['tahun'] < 2000 || $record['tahun'] > (date('Y') + 5)) {
            $errors[] = 'Invalid year: ' . $record['tahun'];
        }

        // More flexible numeric validation
        if (!is_numeric($record['jumlah'])) {
            $errors[] = 'Invalid jumlah value: ' . $record['jumlah'] . ' (not numeric)';
        } elseif ($record['jumlah'] < 0) {
            $errors[] = 'Invalid jumlah value: ' . $record['jumlah'] . ' (negative)';
        }
        
        if (!empty($errors)) {
            $prefix = $lineNumber ? "Line {$lineNumber}: " : "";
            throw new \Exception($prefix . implode(', ', $errors));
        }
    }

    // ... (rest of your helper methods remain the same)
    
    private function processImportDirectly($jobId, $fileInfos, $chunkSize, $validateData)
    {
        $totalRecordsImported = 0;
        $totalErrors = 0;
        $processedFiles = 0;

        foreach ($fileInfos as $index => $fileInfo) {
            $this->updateImportProgress($jobId, [
                'status' => 'processing',
                'current_file' => $index + 1,
                'total_files' => count($fileInfos),
                'message' => "Processing {$fileInfo['original_name']}..."
            ]);

            $result = $this->processCSVFile($fileInfo['stored_path'], $chunkSize, $validateData);
            
            $totalRecordsImported += $result['records_imported'];
            $totalErrors += $result['errors'];
            $processedFiles++;

            // Clean up stored file
            Storage::delete($fileInfo['stored_path']);
        }

        $this->updateImportProgress($jobId, [
            'status' => 'completed',
            'progress' => 100,
            'records_imported' => $totalRecordsImported,
            'errors' => $totalErrors,
            'message' => "Import completed: {$totalRecordsImported} records imported"
        ]);

        return [
            'files_processed' => $processedFiles,
            'records_imported' => $totalRecordsImported,
            'errors' => $totalErrors
        ];
    }

    /**
     * Insert batch of records into database
     */
    private function insertBatch($batch)
    {
        try {
            TbTrade::insert($batch);
            return count($batch);
        } catch (\Exception $e) {
            Log::error('Batch insert failed: ' . $e->getMessage());
            
            // Fallback: insert one by one
            $inserted = 0;
            foreach ($batch as $record) {
                try {
                    TbTrade::create($record);
                    $inserted++;
                } catch (\Exception $individualError) {
                    Log::warning('Individual record insert failed: ' . $individualError->getMessage());
                }
            }
            return $inserted;
        }
    }
    private function initializeImportProgress($jobId, $fileInfos)
    {
        $progress = [
            'status' => 'initialized',
            'progress' => 0,
            'current_file' => 0,
            'total_files' => count($fileInfos),
            'records_processed' => 0,
            'records_imported' => 0,
            'errors' => 0,
            'message' => 'Import initialized',
            'files' => $fileInfos,
            'started_at' => now()->toISOString()
        ];

        Cache::put("csv_import_progress_{$jobId}", $progress, now()->addHours(24));
    }

    private function updateImportProgress($jobId, $updates)
    {
        $progress = Cache::get("csv_import_progress_{$jobId}", []);
        $progress = array_merge($progress, $updates);
        $progress['updated_at'] = now()->toISOString();
        
        Cache::put("csv_import_progress_{$jobId}", $progress, now()->addHours(24));
    }

    private function estimateRecordsCount($file)
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) return 0;

        $lineCount = 0;
        while (fgets($handle) !== false) {
            $lineCount++;
        }
        fclose($handle);

        return max(0, $lineCount - 1); // Subtract header row
    }

    private function estimateProcessingTime($recordCount)
    {
        // Assume ~1000 records per second processing speed
        $seconds = ceil($recordCount / 1000);
        
        if ($seconds < 60) {
            return "{$seconds} seconds";
        } elseif ($seconds < 3600) {
            $minutes = ceil($seconds / 60);
            return "{$minutes} minutes";
        } else {
            $hours = number_format($seconds / 3600, 1);
            return "{$hours} hours";
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}