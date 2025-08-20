<?php
// force_fix_scraper.php - Manually verify and fix the scraper issue

echo "🔧 Force Fix Scraper - Manual Investigation\n";
echo "==========================================\n\n";

$scraperPath = __DIR__ . '/app/Services/Scrapers/TrademapScraper.php';

// Check current content
if (file_exists($scraperPath)) {
    $content = file_get_contents($scraperPath);
    
    echo "📄 Current TrademapScraper.php analysis:\n";
    echo "  File size: " . strlen($content) . " bytes\n";
    
    // Check for debug code
    if (strpos($content, 'DEBUG') !== false) {
        echo "  ❌ STILL contains DEBUG code!\n";
    } else {
        echo "  ✅ No DEBUG code found\n";
    }
    
    // Check for script reference
    if (preg_match('/storage\/app\/([^\'\"]+\.cjs)/', $content, $matches)) {
        echo "  📄 Uses script: " . $matches[1] . "\n";
    }
    
    // Check for debug processing methods
    $debugMethods = [
        'processScrapedData',
        'extractNumericValue.*DEBUG',
        'debugStats',
        'year_values_found',
        'DEBUG Final Stats'
    ];
    
    foreach ($debugMethods as $method) {
        if (preg_match("/{$method}/", $content)) {
            echo "  ❌ Contains debug method: {$method}\n";
        }
    }
    
} else {
    echo "❌ TrademapScraper.php not found!\n";
    exit(1);
}

echo "\n🔧 FORCE REPLACING with clean version...\n";

// The completely clean version
$cleanCode = '<?php
// app/Services/Scrapers/TrademapScraper.php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class TrademapScraper
{
    protected string $baseUrl = \'https://www.trademap.org\';
    protected array $config;

    public function __construct()
    {
        $this->config = [
            \'timeout\' => 180,
            \'retries\' => 3
        ];
    }

    public function scrapeIndonesiaTradeData(): array
    {
        Log::info(\'Starting Trademap scraping\');
        
        try {
            $url = $this->buildTrademapUrl();
            $allYearsData = $this->executePuppeteerScraping($url);
            
            return $this->processMultiYearData($allYearsData);
            
        } catch (Exception $e) {
            Log::error("Scraping error: " . $e->getMessage());
            return [];
        }
    }

    protected function buildTrademapUrl(): string
    {
        $baseUrl = \'https://www.trademap.org/Product_SelCountry_TS.aspx\';
        $params = [
            \'nvpm\' => \'1|360||||TOTAL|||2|1|1|1|2|1|1|1||1\',
            \'dlang\' => \'en\'
        ];
        
        return $baseUrl . \'?\' . http_build_query($params);
    }

    protected function executePuppeteerScraping(string $url): ?string
    {
        try {
            $puppeteerScript = base_path(\'storage/app/fixed_trademap_scraper.cjs\');
            
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
                Log::error("Output: " . implode("\\n", $output));
                return null;
            }
            
            // Find temp file
            $tempFile = null;
            foreach ($output as $line) {
                if (strpos($line, \'/tmp/trademap_fixed_\') !== false) {
                    $tempFile = trim($line);
                    break;
                }
            }
            
            if (!$tempFile || !file_exists($tempFile)) {
                Log::error("No temp file found");
                Log::error("Output: " . implode("\\n", $output));
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
        $years = [2020, 2021, 2022, 2023, 2024];
        
        foreach ($scrapedData as $item) {
            $hsCode = trim($item[\'hsCode\'] ?? \'\');
            $productLabel = trim($item[\'productLabel\'] ?? \'\');
            
            if (!empty($hsCode) && !empty($productLabel)) {
                foreach ($years as $year) {
                    $value = $item["value{$year}"] ?? 0;
                    $importedValue = is_numeric($value) ? (float)$value : 0.0;
                    
                    $processedData[] = [
                        \'negara\' => \'Indonesia\',
                        \'kode_hs\' => $hsCode,
                        \'label\' => $productLabel,
                        \'tahun\' => $year,
                        \'jumlah\' => $importedValue,
                        \'satuan\' => \'USD thousands\',
                        \'sumber_data\' => \'Trademap\',
                        \'scraped_at\' => now()
                    ];
                }
            }
        }
        
        Log::info("Created " . count($processedData) . " database records");
        return $processedData;
    }

    public function saveToDatabase(array $tradeData): int
    {
        if (empty($tradeData)) {
            return 0;
        }
        
        try {
            $chunks = array_chunk($tradeData, 1000);
            $totalInserted = 0;
            
            DB::transaction(function () use ($chunks, &$totalInserted) {
                foreach ($chunks as $chunk) {
                    DB::table(\'tb_trade\')->insert($chunk);
                    $totalInserted += count($chunk);
                }
            });
            
            Log::info("Saved {$totalInserted} records to database");
            return $totalInserted;
            
        } catch (Exception $e) {
            Log::error("Database error: " . $e->getMessage());
            return 0;
        }
    }

    public function execute(): array
    {
        $startTime = microtime(true);
        
        try {
            $tradeData = $this->scrapeIndonesiaTradeData();
            
            if (empty($tradeData)) {
                return [
                    \'success\' => false,
                    \'message\' => \'No data scraped\',
                    \'records_scraped\' => 0,
                    \'records_saved\' => 0
                ];
            }
            
            $recordsSaved = $this->saveToDatabase($tradeData);
            $executionTime = microtime(true) - $startTime;
            
            return [
                \'success\' => true,
                \'message\' => \'Scraping completed successfully\',
                \'records_scraped\' => count($tradeData),
                \'records_saved\' => $recordsSaved,
                \'execution_time\' => round($executionTime, 2),
                \'years_processed\' => [2020, 2021, 2022, 2023, 2024]
            ];
            
        } catch (Exception $e) {
            Log::error("Execution failed: " . $e->getMessage());
            
            return [
                \'success\' => false,
                \'message\' => \'Scraping failed: \' . $e->getMessage(),
                \'records_scraped\' => 0,
                \'records_saved\' => 0
            ];
        }
    }
}';

// Force write the clean version
file_put_contents($scraperPath, $cleanCode);
echo "✅ FORCE WROTE clean TrademapScraper.php\n";

// Verify it was written correctly
$newContent = file_get_contents($scraperPath);
if (strpos($newContent, 'DEBUG') !== false) {
    echo "❌ STILL contains DEBUG after replacement!\n";
} else {
    echo "✅ Clean version confirmed - no DEBUG code\n";
}

echo "\n📁 File permissions:\n";
echo "Current permissions: " . substr(sprintf('%o', fileperms($scraperPath)), -4) . "\n";

// Clear any potential caches
echo "\n🗑️  Clearing caches...\n";
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache cleared\n";
}

// Test the path resolution
echo "\n🧪 Testing path resolution:\n";
try {
    $testPath = __DIR__ . '/vendor/autoload.php';
    require_once $testPath;
    
    // Simulate Laravel base_path
    function base_path($path = '') {
        return __DIR__ . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
    
    $scriptPath = base_path('storage/app/fixed_trademap_scraper.cjs');
    echo "Script path resolves to: {$scriptPath}\n";
    
    if (file_exists($scriptPath)) {
        echo "✅ Script file exists and is accessible\n";
    } else {
        echo "❌ Script file NOT found at resolved path\n";
    }
    
} catch (Exception $e) {
    echo "⚠️  Could not test path resolution: " . $e->getMessage() . "\n";
}

echo "\n🎯 NOW RUN:\n";
echo "php artisan config:clear\n";
echo "php artisan cache:clear\n";
echo "php artisan scrape:trademap --verbose\n";

?>