<?php
// app/Services/Scrapers/TrademapScraper.php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class TrademapScraper
{
    protected string $baseUrl = 'https://www.trademap.org';
    protected array $config;

    public function __construct()
    {
        $this->config = [
            'timeout' => 180,
            'retries' => 3
        ];
    }

    public function scrapeIndonesiaTradeData(string $productCode = 'TOTAL'): array
    {
        Log::info("Starting Trademap scraping for product code: {$productCode}");
        
        try {
            $url = $this->buildTrademapUrl($productCode);
            $allYearsData = $this->executePuppeteerScraping($url);
            
            return $this->processMultiYearData($allYearsData);
            
        } catch (Exception $e) {
            Log::error("Scraping error: " . $e->getMessage());
            return [];
        }
    }

    protected function buildTrademapUrl(string $productCode = 'TOTAL'): string
    {
        $baseUrl = 'https://www.trademap.org/Product_SelCountry_TS.aspx';
        $nvpm = "1|360||||{$productCode}|||2|1|1|1|2|1|1|1||1";
        $params = [
            'nvpm' => $nvpm,
            'dlang' => 'en'
        ];
        
        return $baseUrl . '?' . http_build_query($params);
    }

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
                Log::error("Output: " . implode("\n", $output));
                return null;
            }
            
            // Find temp file
            $tempFile = null;
            foreach ($output as $line) {
                if (strpos($line, '/tmp/trademap_fixed_') !== false) {
                    $tempFile = trim($line);
                    break;
                }
            }
            
            if (!$tempFile || !file_exists($tempFile)) {
                Log::error("No temp file found");
                Log::error("Output: " . implode("\n", $output));
                return null;
            }
            
            $jsonData = file_get_contents($tempFile);
            unlink($tempFile);
            
            $decoded = json_decode($jsonData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("Invalid JSON: " . json_last_error_msg());
                return null;
            }
            
            Log::info("Puppeteer returned " . count($decoded) . " records");
            return $jsonData;
            
        } catch (Exception $e) {
            Log::error("Puppeteer execution failed: " . $e->getMessage());
            return null;
        }
    }

    protected function processMultiYearData(?string $rawData): array
    {
        if (empty($rawData)) {
            Log::error("Empty raw data");
            return [];
        }
        
        $scrapedData = json_decode($rawData, true);
        if (!is_array($scrapedData)) {
            Log::error("Invalid data structure");
            return [];
        }
        
        Log::info("Processing " . count($scrapedData) . " records");
        
        $processedData = [];
        // Dynamic years: Current Year down to Current Year - 6 (7 years total)
        // This ensures we capture historical data (e.g. 2020) even if we are in 2026 but data stops at 2024.
        $currentYear = (int) date('Y');
        $years = [];
        for ($y = $currentYear; $y >= $currentYear - 6; $y--) {
            $years[] = $y;
        }
        
        $skippedCount = 0;
        
        foreach ($scrapedData as $item) {
            $hsCode = trim($item['hsCode'] ?? '');
            $productLabel = trim($item['productLabel'] ?? '');
            
            // ENHANCED VALIDATION - Skip invalid/placeholder data
            $isValid = $this->isValidTradeRecord($hsCode, $productLabel);
            
            if ($isValid) {
                foreach ($years as $year) {
                    // Check if the key exists and is not null (distinguish between 0 and missing)
                    if (!array_key_exists("value{$year}", $item) || $item["value{$year}"] === null) {
                        continue; // Skip years that weren't found in the scrape (preserve historical data)
                    }

                    $value = $item["value{$year}"];
                    $importedValue = is_numeric($value) ? (float)$value : 0.0;
                    
                    $processedData[] = [
                        'negara' => 'Indonesia',
                        'kode_hs' => $hsCode,
                        'label' => $productLabel,
                        'tahun' => $year,
                        'jumlah' => $importedValue,
                        'satuan' => 'USD thousands',
                        'sumber_data' => 'Trademap',
                        'scraped_at' => now()
                    ];
                }
            } else {
                $skippedCount++;
                Log::debug("Skipped invalid record: HS='{$hsCode}', Label='{$productLabel}'");
            }
        }
        
        Log::info("Created " . count($processedData) . " database records, skipped {$skippedCount} invalid records");
        return $processedData;
    }


    /**
     * Simple validation - only skip obvious placeholder data
     */
    protected function isValidTradeRecord(string $hsCode, string $productLabel): bool
    {
        // Skip completely empty records
        if (empty($hsCode) || empty($productLabel)) {
            return false;
        }
        
        // Skip ONLY obvious placeholder patterns where both are single digits
        $obviousPlaceholders = [
            ['1', '2'],
            ['2', '3'], 
            ['3', '4'],
            ['4', '1'],
            ['1234', '1234']
        ];
        
        foreach ($obviousPlaceholders as [$hsPattern, $labelPattern]) {
            if ($hsCode === $hsPattern && $productLabel === $labelPattern) {
                return false;
            }
        }
        
        // Skip if label is ONLY a single digit
        if (strlen($productLabel) === 1 && is_numeric($productLabel)) {
            return false;
        }
        
        // Everything else is VALID including:
        // - HS codes like "27", "76", "31" (legitimate trade categories)
        // - Labels like "Fertilisers", "Aluminium and articles thereof"
        
        return true;
    }

    public function saveToDatabase(array $tradeData): int
    {
        if (empty($tradeData)) {
            return 0;
        }

        try {
            $chunks = array_chunk($tradeData, 1000);
            $totalAffected = 0;

            foreach ($chunks as $chunk) {
                // Use upsert to insert new records or update existing ones.
                $affectedRows = DB::table('tb_trade')->upsert(
                    $chunk,
                    ['negara', 'kode_hs', 'tahun'], // Unique constraints
                    ['jumlah', 'label', 'satuan', 'scraped_at'] // Columns to update on duplicate
                );
                $totalAffected += $affectedRows;
            }

            Log::info("Upserted {$totalAffected} records to database. (This number includes inserts and updates)");
            return $totalAffected;

        } catch (Exception $e) {
            Log::error("Database error during upsert: " . $e->getMessage());
            return 0;
        }
    }

    public function execute(string $productCode = 'TOTAL'): array
    {
        $startTime = microtime(true);
        
        try {
            $tradeData = $this->scrapeIndonesiaTradeData($productCode);
            
            if (empty($tradeData)) {
                return [
                    'success' => false,
                    'message' => 'No data scraped',
                    'records_scraped' => 0,
                    'records_saved' => 0
                ];
            }
            
            $recordsSaved = $this->saveToDatabase($tradeData);
            $executionTime = microtime(true) - $startTime;
            
            // Dynamic years list for response
            $currentYear = (int) date('Y');
            $processedYears = [];
            for ($y = $currentYear; $y >= $currentYear - 6; $y--) {
                $processedYears[] = $y;
            }
            
            return [
                'success' => true,
                'message' => 'Scraping completed successfully',
                'records_scraped' => count($tradeData),
                'records_saved' => $recordsSaved,
                'execution_time' => round($executionTime, 2),
                'years_processed' => $processedYears
            ];
            
        } catch (Exception $e) {
            Log::error("Execution failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Scraping failed: ' . $e->getMessage(),
                'records_scraped' => 0,
                'records_saved' => 0
            ];
        }
    }
}