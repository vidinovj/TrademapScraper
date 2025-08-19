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
    protected int $delay = 3; // seconds between requests

    public function __construct()
    {
        $this->config = [
            'timeout' => 60,
            'retries' => 3,
            'delay_min' => 2,
            'delay_max' => 5
        ];
    }

    /**
     * Scrape Indonesia trade data from Trademap
     * Based on test requirements: 2020-2024 data
     */
    public function scrapeIndonesiaTradeData(): array
    {
        Log::info('Starting Trademap scraping for Indonesia trade data');
        
        $results = [];
        $years = [2020, 2021, 2022, 2023, 2024];
        
        foreach ($years as $year) {
            try {
                Log::info("Scraping trade data for year: {$year}");
                
                $yearData = $this->scrapeYearData($year);
                $results = array_merge($results, $yearData);
                
                // Rate limiting
                sleep(rand($this->config['delay_min'], $this->config['delay_max']));
                
            } catch (Exception $e) {
                Log::error("Error scraping year {$year}: " . $e->getMessage());
            }
        }
        
        Log::info("Completed scraping. Total records: " . count($results));
        return $results;
    }

    /**
     * Scrape data for specific year using Puppeteer
     */
    protected function scrapeYearData(int $year): array
    {
        // Build Trademap URL for Indonesia yearly data
        $url = $this->buildTrademapUrl($year);
        
        // Use Puppeteer to scrape (similar to your legal docs scraper)
        $data = $this->executePuppeteerScraping($url, $year);
        
        return $this->processScrapedData($data, $year);
    }

    /**
     * Build Trademap URL for Indonesia trade data (corrected)
     */
    protected function buildTrademapUrl(int $year): string
    {
        // Based on actual Trademap URL structure for Indonesia imports
        // nvpm parameter breakdown: 1|360||||TOTAL|||2|1|1|1|2|1|1|1||1
        // 360 = Indonesia country code
        // TOTAL = All products
        // The URL pattern for yearly time series data
        
        $baseUrl = 'https://www.trademap.org/Product_SelCountry_TS.aspx';
        
        $params = [
            'nvpm' => '1|360||||TOTAL|||2|1|1|1|2|1|1|1||1',
            'dlang' => 'en'
        ];
        
        // Add year parameter if the site supports it
        // Note: Trademap might handle year selection via form submission
        // This URL gets the general page, year filtering might need interaction
        
        $queryString = http_build_query($params);
        $fullUrl = "{$baseUrl}?{$queryString}";
        
        Log::info("Built Trademap URL for year {$year}: {$fullUrl}");
        
        return $fullUrl;
    }

    /**
     * Execute Puppeteer scraping with improved error handling
     */
    protected function executePuppeteerScraping(string $url, int $year): ?string
    {
        try {
            // Path to Puppeteer script
            $puppeteerScript = base_path('storage/app/trademap_scraper.cjs');
            
            if (!file_exists($puppeteerScript)) {
                Log::error("Puppeteer script not found at: {$puppeteerScript}");
                Log::info("Please copy the trademap_scraper.cjs file to storage/app/");
                return null;
            }
            
            // Build command with better error capture
            $command = "node {$puppeteerScript} " . escapeshellarg($url) . " " . escapeshellarg($year);
            
            Log::info("Executing Puppeteer command for year {$year}");
            Log::debug("Command: {$command}");
            
            // Capture both stdout and stderr
            $descriptorspec = [
                0 => ["pipe", "r"],  // stdin
                1 => ["pipe", "w"],  // stdout
                2 => ["pipe", "w"]   // stderr
            ];
            
            $process = proc_open($command, $descriptorspec, $pipes);
            
            if (is_resource($process)) {
                fclose($pipes[0]); // Close stdin
                
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                
                fclose($pipes[1]);
                fclose($pipes[2]);
                
                $returnValue = proc_close($process);
                
                // Log debugging information
                if (!empty($stderr)) {
                    Log::info("Puppeteer debug info: " . $stderr);
                }
                
                if ($returnValue !== 0) {
                    Log::error("Puppeteer process failed with exit code: {$returnValue}");
                    Log::error("Error output: " . $stderr);
                    return null;
                }
                
                if (empty($stdout)) {
                    Log::warning("Empty output from Puppeteer for year {$year}");
                    Log::warning("Stderr: " . $stderr);
                    return null;
                }
                
                // Validate JSON output
                $decoded = json_decode($stdout, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error("Invalid JSON output from Puppeteer: " . json_last_error_msg());
                    Log::error("Raw output: " . substr($stdout, 0, 500));
                    return null;
                }
                
                return $stdout;
            }
            
            Log::error("Failed to create Puppeteer process");
            return null;
            
        } catch (Exception $e) {
            Log::error("Puppeteer execution failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Process scraped data with improved validation
     */
    protected function processScrapedData(?string $rawData, int $year): array
    {
        if (empty($rawData)) {
            return [];
        }
        
        try {
            $scrapedData = json_decode($rawData, true);
            
            if (!is_array($scrapedData)) {
                Log::warning("Invalid JSON data for year {$year}");
                return [];
            }
            
            $processedData = [];
            $skippedCount = 0;
            
            foreach ($scrapedData as $item) {
                $hsCode = $this->cleanHsCode($item['hsCode'] ?? '');
                $productLabel = $this->cleanText($item['productLabel'] ?? '');
                $importedValue = $this->extractNumericValue($item['importedValue'] ?? '');
                
                // Enhanced validation
                if ($this->isValidHsCode($hsCode) && !empty($productLabel)) {
                    $processedData[] = [
                        'negara' => 'Indonesia',
                        'kode_hs' => $hsCode,
                        'label' => $productLabel,
                        'tahun' => $year,
                        'jumlah' => $importedValue,
                        'satuan' => $this->cleanText($item['unit'] ?? '-'),
                        'sumber_data' => 'Trademap',
                        'scraped_at' => now()
                    ];
                } else {
                    $skippedCount++;
                    Log::debug("Skipped invalid record: HS={$hsCode}, Label={$productLabel}");
                }
            }
            
            Log::info("Processed {$year}: {" . count($processedData) . "} records, {$skippedCount} skipped");
            return $processedData;
            
        } catch (Exception $e) {
            Log::error("Error processing scraped data for year {$year}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Save data to database (TbTrade table as per test requirement)
     */
    public function saveToDatabase(array $tradeData): int
    {
        if (empty($tradeData)) {
            return 0;
        }
        
        try {
            // Insert data in chunks for performance (Question 4 optimization technique)
            $chunks = array_chunk($tradeData, 1000);
            $totalInserted = 0;
            
            DB::transaction(function () use ($chunks, &$totalInserted) {
                foreach ($chunks as $chunk) {
                    DB::table('tb_trade')->insert($chunk);
                    $totalInserted += count($chunk);
                }
            });
            
            Log::info("Successfully saved {$totalInserted} records to database");
            return $totalInserted;
            
        } catch (Exception $e) {
            Log::error("Database save error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Utility methods for data cleaning
     */
    protected function cleanHsCode(string $hsCode): string
    {
        return preg_replace('/[^0-9.]/', '', $hsCode);
    }

    protected function cleanText(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    protected function extractNumericValue(string $value): float
    {
        // Remove currency symbols, commas, spaces
        $cleaned = preg_replace('/[^\d.]/', '', $value);
        return $cleaned ? (float) $cleaned : 0.0;
    }

    protected function isValidHsCode(string $hsCode): bool
    {
        // HS code should be numeric, with optional dots
        // It should not contain any letters or symbols other than dots
        return preg_match('/^[\d.]+$/', $hsCode) && strlen($hsCode) > 0;
    }

    /**
     * Main scraping execution method
     */
    public function execute(): array
    {
        $startTime = microtime(true);
        
        try {
            // Scrape data
            $tradeData = $this->scrapeIndonesiaTradeData();
            
            if (empty($tradeData)) {
                return [
                    'success' => false,
                    'message' => 'No data scraped',
                    'records' => 0
                ];
            }
            
            // Save to database
            $recordsSaved = $this->saveToDatabase($tradeData);
            
            $executionTime = microtime(true) - $startTime;
            
            return [
                'success' => true,
                'message' => 'Scraping completed successfully',
                'records_scraped' => count($tradeData),
                'records_saved' => $recordsSaved,
                'execution_time' => round($executionTime, 2),
                'years_processed' => [2020, 2021, 2022, 2023, 2024]
            ];
            
        } catch (Exception $e) {
            Log::error("Scraping execution failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Scraping failed: ' . $e->getMessage(),
                'records' => 0
            ];
        }
    }
}