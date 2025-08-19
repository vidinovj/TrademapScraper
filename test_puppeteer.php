<?php
// test_puppeteer.php
// Quick test script to debug Puppeteer scraping

require_once __DIR__ . '/vendor/autoload.php';

$testUrl = 'https://www.trademap.org/Product_SelCountry_TS.aspx?nvpm=1%7c360%7c%7c%7c%7cTOTAL%7c%7c%7c2%7c1%7c1%7c1%7c2%7c1%7c1%7c1%7c%7c1';

echo "🧪 Testing Puppeteer Scraper\n";
echo "==========================\n\n";

// Check if Node.js is available
echo "1. Checking Node.js...\n";
$nodeVersion = shell_exec('node --version 2>&1');
if (empty($nodeVersion)) {
    echo "❌ Node.js not found. Please install Node.js first.\n";
    exit(1);
}
echo "✅ Node.js version: " . trim($nodeVersion) . "\n\n";

// Check if Puppeteer is installed
echo "2. Checking Puppeteer...\n";
$puppeteerCheck = shell_exec('npm list puppeteer 2>&1');
if (strpos($puppeteerCheck, 'puppeteer@') === false) {
    echo "❌ Puppeteer not found. Please run: npm install puppeteer\n";
    exit(1);
}
echo "✅ Puppeteer is installed\n\n";

// Check if script exists
echo "3. Checking scraper script...\n";
$scriptPath = __DIR__ . '/storage/app/trademap_scraper.cjs';
if (!file_exists($scriptPath)) {
    echo "❌ Scraper script not found at: {$scriptPath}\n";
    echo "Please copy the trademap_scraper.js file to storage/app/\n";
    exit(1);
}
echo "✅ Scraper script found\n\n";

// Test the scraper
echo "4. Testing scraper with sample URL...\n";
echo "URL: {$testUrl}\n\n";

$command = "node {$scriptPath} " . escapeshellarg($testUrl) . " 2024";

echo "Executing: {$command}\n\n";

// Capture both stdout and stderr
$descriptorspec = [
    0 => ["pipe", "r"],  // stdin
    1 => ["pipe", "w"],  // stdout  
    2 => ["pipe", "w"]   // stderr
];

$process = proc_open($command, $descriptorspec, $pipes);

if (is_resource($process)) {
    fclose($pipes[0]);
    
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    $returnValue = proc_close($process);
    
    echo "=== DEBUG OUTPUT ===\n";
    echo $stderr . "\n";
    
    echo "=== JSON DATA ===\n";
    if (empty($stdout)) {
        echo "❌ No data returned\n";
    } else {
        $data = json_decode($stdout, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✅ Valid JSON returned\n";
            echo "Records found: " . count($data) . "\n";
            
            if (count($data) > 0) {
                echo "\nSample record:\n";
                print_r($data[0]);
                
                // Check for common issues
                $firstRecord = $data[0];
                if (isset($firstRecord['hsCode'])) {
                    if (preg_match('/country|total/i', $firstRecord['hsCode'])) {
                        echo "\n⚠️  WARNING: hsCode contains '{$firstRecord['hsCode']}' - this suggests wrong column selection\n";
                    } else {
                        echo "\n✅ hsCode looks valid: {$firstRecord['hsCode']}\n";
                    }
                }
            }
        } else {
            echo "❌ Invalid JSON: " . json_last_error_msg() . "\n";
            echo "Raw output: " . substr($stdout, 0, 500) . "\n";
        }
    }
    
    echo "\nExit code: {$returnValue}\n";
    
} else {
    echo "❌ Failed to execute command\n";
}

echo "\n🎯 Next Steps:\n";
echo "1. If data looks good, run: php artisan scrape:trademap --verbose\n";
echo "2. If hsCode contains countries, the table structure needs adjustment\n";
echo "3. Check the debug output above for table structure info\n";