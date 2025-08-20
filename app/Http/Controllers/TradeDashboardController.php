<?php

namespace App\Http\Controllers;

use App\Models\TbTrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TradeDashboardController extends Controller
{
    /**
     * Display the trade data dashboard (Pustik style)
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search = $request->get('search', '');
        
        // Get aggregated trade data by HS code with yearly breakdown
        $query = TbTrade::select([
            'kode_hs',
            DB::raw('MAX(label) as product_label'),
            DB::raw('SUM(CASE WHEN tahun = 2020 THEN jumlah ELSE 0 END) as value_2020'),
            DB::raw('SUM(CASE WHEN tahun = 2021 THEN jumlah ELSE 0 END) as value_2021'),
            DB::raw('SUM(CASE WHEN tahun = 2022 THEN jumlah ELSE 0 END) as value_2022'),
            DB::raw('SUM(CASE WHEN tahun = 2023 THEN jumlah ELSE 0 END) as value_2023'),
            DB::raw('SUM(CASE WHEN tahun = 2024 THEN jumlah ELSE 0 END) as value_2024'),
            DB::raw('SUM(jumlah) as total_value')
        ])
        ->groupBy('kode_hs');
        
        // Apply search filter
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('kode_hs', 'LIKE', "%{$search}%")
                  ->orWhere('label', 'LIKE', "%{$search}%");
            });
        }
        
        // Order by total value descending (show highest imports first)
        $query->orderByDesc('total_value');
        
        $tradeData = $query->paginate($perPage);
        
        // Get summary statistics for Pustik-style cards
        $summaryStats = $this->getSummaryStatistics();
        
        // Get top sectors for additional insights
        $topSectors = $this->getTopSectors();
        
        return view('dashboard.trade-data', compact(
            'tradeData', 
            'summaryStats', 
            'topSectors',
            'search',
            'perPage'
        ));
    }
    
    /**
     * Get summary statistics for the dashboard
     */
    private function getSummaryStatistics()
    {
        $totalRecords = TbTrade::count();
        $totalValue2024 = TbTrade::where('tahun', 2024)->sum('jumlah');
        $totalHsCodes = TbTrade::distinct('kode_hs')->count();
        $lastUpdate = TbTrade::latest('scraped_at')->first()?->scraped_at;
        
        return [
            'total_records' => $totalRecords,
            'total_value_2024' => $totalValue2024,
            'total_hs_codes' => $totalHsCodes,
            'last_update' => $lastUpdate
        ];
    }
    
    /**
     * Get top trading sectors
     */
    private function getTopSectors()
    {
        return TbTrade::select([
            DB::raw('LEFT(kode_hs, 2) as sector_code'),
            DB::raw('MAX(label) as sector_name'),
            DB::raw('SUM(jumlah) as total_value'),
            DB::raw('COUNT(*) as record_count')
        ])
        ->where('tahun', 2024)
        ->groupBy(DB::raw('LEFT(kode_hs, 2)'))
        ->orderByDesc('total_value')
        ->limit(10)
        ->get();
    }
    
    /**
     * Export trade data to CSV in TbTrade format (compatible with import)
     * Now creates perfect export → import cycle for Data Engineer demo
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
            
            // Add BOM for proper UTF-8 encoding in Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // CSV headers - exactly matching import format for perfect cycle
            fputcsv($file, [
                'negara',
                'kode_hs', 
                'label',
                'tahun',
                'jumlah',
                'satuan',
                'sumber_data'
            ]);
            
            // CSV data - ready for re-import
            foreach ($data as $row) {
                fputcsv($file, [
                    $row->negara,
                    $row->kode_hs,
                    $row->label,
                    $row->tahun,
                    $row->jumlah, // Keep as numeric for import compatibility
                    $row->satuan,
                    $row->sumber_data
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Validate CSV files before import (addresses Data Engineer Test requirements)
     */
    public function validateCsv(Request $request)
    {
        $request->validate([
            'csv_files.*' => 'required|file|mimes:csv,txt|max:102400' // 100MB per file
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
     * Show the CSV import page (addresses Data Engineer Test Questions 3 & 4)
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

    public function importCsv(Request $request)
    {
        $request->validate([
            'csv_files.*' => 'required|file|mimes:csv,txt|max:102400', // 100MB per file
            'chunk_size' => 'integer|min:100|max:10000',
            'validate_data' => 'boolean'
        ]);

        try {
            $chunkSize = $request->get('chunk_size', 1000); // Default 1000 records per batch
            $validateData = $request->get('validate_data', true);
            $jobId = Str::uuid();
            
            $fileInfos = [];
            $totalFiles = count($request->file('csv_files'));

            // Store files temporarily and prepare for processing
            foreach ($request->file('csv_files') as $index => $file) {
                $filename = $jobId . '_' . $index . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('csv-imports', $filename, 'local');
                
                $fileInfos[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'file_size' => $file->getSize(),
                    'estimated_records' => $this->estimateRecordsCount($file)
                ];
            }

            // Initialize progress tracking
            $this->initializeImportProgress($jobId, $fileInfos);

            // For large files (>50GB equivalent), use queue processing (Question 4 optimization)
            $totalSize = array_sum(array_column($fileInfos, 'file_size'));
            
            if ($totalSize > 50 * 1024 * 1024) { // >50MB triggers background processing
                // Dispatch background job for large imports
                ProcessCsvImportJob::dispatch($jobId, $fileInfos, $chunkSize, $validateData);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Large file import queued for background processing',
                    'job_id' => $jobId,
                    'processing_mode' => 'background',
                    'total_files' => $totalFiles,
                    'check_progress_url' => route('dashboard.import-progress', $jobId)
                ]);
                
            } else {
                // Process smaller files directly
                $result = $this->processImportDirectly($jobId, $fileInfos, $chunkSize, $validateData);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Import completed successfully',
                    'job_id' => $jobId,
                    'processing_mode' => 'direct',
                    'result' => $result
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
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
     * Validate single CSV file structure and content
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

            // Read first line (headers)
            $headers = fgetcsv($handle);
            if ($headers === false) {
                throw new \Exception('Cannot read CSV headers');
            }

            $validation['columns_found'] = $headers;

            // Validate required columns for TbTrade (from Data Engineer test)
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

            // Read sample data (first 3 rows)
            $sampleCount = 0;
            while (($row = fgetcsv($handle)) !== false && $sampleCount < 3) {
                $validation['sample_data'][] = array_combine($headers, $row);
                $sampleCount++;
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
     * Process CSV import directly (for smaller files)
     */
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
     * Process single CSV file with chunking (optimization for Question 4)
     */
    private function processCSVFile($filePath, $chunkSize, $validateData)
    {
        $fullPath = Storage::path($filePath);
        $handle = fopen($fullPath, 'r');
        
        if ($handle === false) {
            throw new \Exception("Cannot open file: {$filePath}");
        }

        $headers = fgetcsv($handle); // Skip header row
        $recordsImported = 0;
        $errors = 0;
        $batch = [];

        while (($row = fgetcsv($handle)) !== false) {
            try {
                $record = $this->mapCsvRowToTbTrade($headers, $row, $validateData);
                if ($record) {
                    $batch[] = $record;
                }

                // Process batch when chunk size reached
                if (count($batch) >= $chunkSize) {
                    $imported = $this->insertBatch($batch);
                    $recordsImported += $imported;
                    $batch = [];
                }

            } catch (\Exception $e) {
                $errors++;
                // Log error but continue processing
                \Log::warning("CSV import row error: " . $e->getMessage());
            }
        }

        // Process remaining records
        if (!empty($batch)) {
            $imported = $this->insertBatch($batch);
            $recordsImported += $imported;
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
    private function mapCsvRowToTbTrade($headers, $row, $validateData)
    {
        $data = array_combine($headers, $row);
        
        $record = [
            'negara' => $data['negara'] ?? 'UNKNOWN',
            'kode_hs' => $data['kode_hs'] ?? '',
            'label' => $data['label'] ?? '',
            'tahun' => (int)($data['tahun'] ?? date('Y')),
            'jumlah' => floatval($data['jumlah'] ?? 0),
            'satuan' => $data['satuan'] ?? '-',
            'sumber_data' => $data['sumber_data'] ?? 'CSV Import',
            'scraped_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ];

        // Validation if enabled
        if ($validateData) {
            if (empty($record['kode_hs']) || empty($record['label'])) {
                throw new \Exception('Missing required fields: kode_hs or label');
            }
            
            if ($record['tahun'] < 2000 || $record['tahun'] > date('Y') + 1) {
                throw new \Exception('Invalid year: ' . $record['tahun']);
            }
        }

        return $record;
    }

    /**
     * Bulk insert batch with optimization (Question 4)
     */
    private function insertBatch($batch)
    {
        try {
            // Optimize for large inserts
            DB::statement('SET foreign_key_checks=0');
            TbTrade::insert($batch);
            DB::statement('SET foreign_key_checks=1');
            
            return count($batch);
        } catch (\Exception $e) {
            \Log::error('Batch insert failed: ' . $e->getMessage());
            
            // Fallback: try inserting one by one
            $inserted = 0;
            foreach ($batch as $record) {
                try {
                    TbTrade::create($record);
                    $inserted++;
                } catch (\Exception $individualError) {
                    \Log::warning('Individual record insert failed: ' . $individualError->getMessage());
                }
            }
            return $inserted;
        }
    }

    /**
     * Initialize import progress tracking
     */
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

    /**
     * Update import progress
     */
    private function updateImportProgress($jobId, $updates)
    {
        $progress = Cache::get("csv_import_progress_{$jobId}", []);
        $progress = array_merge($progress, $updates);
        $progress['updated_at'] = now()->toISOString();
        
        Cache::put("csv_import_progress_{$jobId}", $progress, now()->addHours(24));
    }

    /**
     * Estimate number of records in CSV
     */
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

    /**
     * Estimate processing time based on record count
     */
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

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}