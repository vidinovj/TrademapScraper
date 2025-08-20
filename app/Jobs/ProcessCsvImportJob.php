<?php

namespace App\Jobs;

use App\Models\TbTrade;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Background job for processing large CSV imports
 * Addresses Data Engineer Test Question 4: Optimization for 50GB+ files
 */
class ProcessCsvImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 7200; // 2 hours timeout for large files
    public $tries = 3;
    public $maxExceptions = 3;

    protected $jobId;
    protected $fileInfos;
    protected $chunkSize;
    protected $validateData;

    /**
     * Create a new job instance.
     */
    public function __construct($jobId, $fileInfos, $chunkSize = 1000, $validateData = true)
    {
        $this->jobId = $jobId;
        $this->fileInfos = $fileInfos;
        $this->chunkSize = $chunkSize;
        $this->validateData = $validateData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting CSV import job: {$this->jobId}");
        
        $this->updateProgress([
            'status' => 'processing',
            'message' => 'Starting background import process...'
        ]);

        $totalRecordsImported = 0;
        $totalErrors = 0;
        $processedFiles = 0;

        try {
            // Process each file
            foreach ($this->fileInfos as $index => $fileInfo) {
                $this->updateProgress([
                    'current_file' => $index + 1,
                    'message' => "Processing file: {$fileInfo['original_name']}..."
                ]);

                $result = $this->processLargeCSVFile($fileInfo);
                
                $totalRecordsImported += $result['records_imported'];
                $totalErrors += $result['errors'];
                $processedFiles++;

                // Update progress
                $progress = (($index + 1) / count($this->fileInfos)) * 100;
                $this->updateProgress([
                    'progress' => round($progress, 1),
                    'records_imported' => $totalRecordsImported,
                    'errors' => $totalErrors
                ]);

                // Clean up processed file
                Storage::delete($fileInfo['stored_path']);
                
                Log::info("Processed file {$fileInfo['original_name']}: {$result['records_imported']} records imported");
            }

            // Mark as completed
            $this->updateProgress([
                'status' => 'completed',
                'progress' => 100,
                'records_imported' => $totalRecordsImported,
                'errors' => $totalErrors,
                'message' => "Import completed successfully: {$totalRecordsImported} records imported"
            ]);

            Log::info("CSV import job completed: {$this->jobId}. Total records: {$totalRecordsImported}");

        } catch (\Exception $e) {
            Log::error("CSV import job failed: {$this->jobId}. Error: " . $e->getMessage());
            
            $this->updateProgress([
                'status' => 'failed',
                'message' => 'Import failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Process large CSV file with memory optimization
     */
    private function processLargeCSVFile($fileInfo)
    {
        $fullPath = Storage::path($fileInfo['stored_path']);
        
        if (!file_exists($fullPath)) {
            throw new \Exception("File not found: {$fileInfo['stored_path']}");
        }

        // Open file with read-only mode for memory efficiency
        $handle = fopen($fullPath, 'r');
        if ($handle === false) {
            throw new \Exception("Cannot open file: {$fileInfo['stored_path']}");
        }

        $headers = fgetcsv($handle); // Read headers
        $recordsImported = 0;
        $errors = 0;
        $batch = [];
        $lineNumber = 1; // Track line for error reporting

        Log::info("Starting to process large file: {$fileInfo['original_name']}");

        try {
            // Process file line by line to manage memory
            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;
                
                try {
                    $record = $this->mapCsvRowToRecord($headers, $row);
                    if ($record) {
                        $batch[] = $record;
                    }

                    // Process batch when chunk size reached (Question 4 optimization)
                    if (count($batch) >= $this->chunkSize) {
                        $imported = $this->optimizedBatchInsert($batch);
                        $recordsImported += $imported;
                        $batch = []; // Clear batch to free memory
                        
                        // Force garbage collection for large files
                        if ($recordsImported % 10000 === 0) {
                            gc_collect_cycles();
                            
                            // Log progress for very large files
                            Log::info("Progress: {$recordsImported} records imported from {$fileInfo['original_name']}");
                        }
                    }

                } catch (\Exception $e) {
                    $errors++;
                    Log::warning("Error processing line {$lineNumber} in {$fileInfo['original_name']}: " . $e->getMessage());
                    
                    // Skip individual bad records but continue processing
                    continue;
                }
            }

            // Process remaining records in final batch
            if (!empty($batch)) {
                $imported = $this->optimizedBatchInsert($batch);
                $recordsImported += $imported;
            }

        } finally {
            fclose($handle);
        }

        Log::info("Completed processing {$fileInfo['original_name']}: {$recordsImported} records imported, {$errors} errors");

        return [
            'records_imported' => $recordsImported,
            'errors' => $errors
        ];
    }

    /**
     * Map CSV row to TbTrade record
     */
    private function mapCsvRowToRecord($headers, $row)
    {
        if (count($headers) !== count($row)) {
            throw new \Exception('Column count mismatch');
        }

        $data = array_combine($headers, $row);
        
        $record = [
            'negara' => trim($data['negara'] ?? 'UNKNOWN'),
            'kode_hs' => trim($data['kode_hs'] ?? ''),
            'label' => trim($data['label'] ?? ''),
            'tahun' => (int)($data['tahun'] ?? date('Y')),
            'jumlah' => $this->parseNumeric($data['jumlah'] ?? 0),
            'satuan' => trim($data['satuan'] ?? '-'),
            'sumber_data' => trim($data['sumber_data'] ?? 'CSV Import'),
            'scraped_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ];

        // Data validation if enabled
        if ($this->validateData) {
            $this->validateRecord($record);
        }

        return $record;
    }

    /**
     * Validate individual record
     */
    private function validateRecord($record)
    {
        if (empty($record['kode_hs'])) {
            throw new \Exception('Missing required field: kode_hs');
        }
        
        if (empty($record['label'])) {
            throw new \Exception('Missing required field: label');
        }
        
        if ($record['tahun'] < 2000 || $record['tahun'] > date('Y') + 5) {
            throw new \Exception('Invalid year: ' . $record['tahun']);
        }

        if (!is_numeric($record['jumlah']) || $record['jumlah'] < 0) {
            throw new \Exception('Invalid jumlah value: ' . $record['jumlah']);
        }
    }

    /**
     * Parse numeric values safely
     */
    private function parseNumeric($value)
    {
        // Remove common thousand separators and handle different decimal formats
        $cleaned = preg_replace('/[,\s]/', '', trim($value));
        $cleaned = str_replace(',', '.', $cleaned); // Handle European decimal format
        
        return is_numeric($cleaned) ? floatval($cleaned) : 0;
    }

    /**
     * Optimized batch insert for large datasets (Question 4 optimizations)
     */
    private function optimizedBatchInsert($batch)
    {
        try {
            // Optimization 1: Disable foreign key checks temporarily
            DB::statement('SET foreign_key_checks=0');
            
            // Optimization 2: Disable autocommit for batch
            DB::beginTransaction();
            
            // Optimization 3: Use raw insert for better performance
            TbTrade::insert($batch);
            
            // Commit transaction
            DB::commit();
            
            // Re-enable foreign key checks
            DB::statement('SET foreign_key_checks=1');
            
            return count($batch);
            
        } catch (\Exception $e) {
            DB::rollback();
            DB::statement('SET foreign_key_checks=1');
            
            Log::error('Batch insert failed: ' . $e->getMessage());
            
            // Fallback: Insert records individually to identify problematic records
            return $this->fallbackIndividualInsert($batch);
        }
    }

    /**
     * Fallback method: insert records one by one
     */
    private function fallbackIndividualInsert($batch)
    {
        $inserted = 0;
        
        foreach ($batch as $record) {
            try {
                TbTrade::create($record);
                $inserted++;
            } catch (\Exception $e) {
                Log::warning('Individual record insert failed: ' . $e->getMessage(), [
                    'record' => $record
                ]);
            }
        }
        
        return $inserted;
    }

    /**
     * Update job progress
     */
    private function updateProgress($updates)
    {
        $progress = Cache::get("csv_import_progress_{$this->jobId}", []);
        $progress = array_merge($progress, $updates);
        $progress['updated_at'] = now()->toISOString();
        
        Cache::put("csv_import_progress_{$this->jobId}", $progress, now()->addHours(24));
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("CSV import job failed permanently: {$this->jobId}", [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        $this->updateProgress([
            'status' => 'failed',
            'message' => 'Import failed: ' . $exception->getMessage(),
            'error' => $exception->getMessage(),
            'failed_at' => now()->toISOString()
        ]);

        // Clean up remaining files
        foreach ($this->fileInfos as $fileInfo) {
            try {
                Storage::delete($fileInfo['stored_path']);
            } catch (\Exception $e) {
                Log::warning("Failed to clean up file: {$fileInfo['stored_path']}");
            }
        }
    }
}