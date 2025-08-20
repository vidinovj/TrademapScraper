<?php
// quick_fix_laravel.php - Automatically fix the Laravel scraper setup

echo "🔧 Quick Fix for Laravel Scraper\n";
echo "================================\n\n";

// Step 1: Ensure the working script is in the right place
$sourceScript = __DIR__ . '/storage/app/fixed_trademap_scraper.cjs';
$testWorkingScript = null;

// Find the working script from the test
$possibleScripts = [
    __DIR__ . '/storage/app/fixed_trademap_scraper.cjs',
    '/tmp/fixed_trademap_scraper.cjs'
];

foreach ($possibleScripts as $script) {
    if (file_exists($script)) {
        $content = file_get_contents($script);
        if (strpos($content, 'multi-row header detection') !== false) {
            $testWorkingScript = $script;
            echo "✅ Found working script at: {$script}\n";
            break;
        }
    }
}

if (!$testWorkingScript) {
    echo "❌ Cannot find the working fixed_trademap_scraper.cjs\n";
    echo "Please save the working script to storage/app/fixed_trademap_scraper.cjs\n";
    exit(1);
}

// Copy the working script to the correct location
$targetScript = __DIR__ . '/storage/app/fixed_trademap_scraper.cjs';
if ($testWorkingScript !== $targetScript) {
    copy($testWorkingScript, $targetScript);
    echo "✅ Copied working script to: {$targetScript}\n";
}

// Step 2: Update TrademapScraper.php to use the correct script
$scraperPath = __DIR__ . '/app/Services/Scrapers/TrademapScraper.php';

if (!file_exists($scraperPath)) {
    echo "❌ TrademapScraper.php not found - please save the final working version\n";
    exit(1);
}

$scraperContent = file_get_contents($scraperPath);

// Check if it's already using the fixed script
if (strpos($scraperContent, 'fixed_trademap_scraper.cjs') !== false) {
    echo "✅ TrademapScraper.php already references fixed script\n";
} else {
    // Update the script reference
    $patterns = [
        '/trademap_scraper\.cjs/',
        '/enhanced_debug_trademap_scraper\.cjs/',
        '/session_aware_debug_scraper\.cjs/',
        '/optimized_trademap_scraper\.cjs/'
    ];
    
    $replacement = 'fixed_trademap_scraper.cjs';
    $updated = false;
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $scraperContent)) {
            $scraperContent = preg_replace($pattern, $replacement, $scraperContent);
            $updated = true;
            echo "✅ Updated script reference to fixed_trademap_scraper.cjs\n";
            break;
        }
    }
    
    if (!$updated) {
        echo "⚠️  Could not find script reference to update in TrademapScraper.php\n";
    }
}

// Remove debug code that might interfere
$debugPatterns = [
    '/Log::info\("DEBUG[^"]*"[^;]*\);?\s*\n?/i',
    '/\$debugStats[^;]*;?\s*\n?/',
    '/\/\/ DEBUG:[^\n]*\n?/',
    '/console\.error\([^)]*DEBUG[^)]*\);?\s*\n?/',
];

foreach ($debugPatterns as $pattern) {
    $scraperContent = preg_replace($pattern, '', $scraperContent);
}

// Write the updated scraper
file_put_contents($scraperPath, $scraperContent);
echo "✅ Updated TrademapScraper.php\n";

// Step 3: Test the setup
echo "\n🧪 Testing the fixed setup...\n";

$testUrl = 'https://www.trademap.org/Product_SelCountry_TS.aspx?nvpm=1%7c360%7c%7c%7c%7cTOTAL%7c%7c%7c2%7c1%7c1%7c1%7c2%7c1%7c1%7c1%7c%7c1';

$command = "node {$targetScript} " . escapeshellarg($testUrl);
echo "Running: {$command}\n";

$output = [];
$returnCode = 0;
exec($command, $output, $returnCode);

$tempFile = null;
foreach ($output as $line) {
    if (strpos($line, '/tmp/trademap_fixed_') !== false) {
        $tempFile = trim($line);
        break;
    }
}

if ($tempFile && file_exists($tempFile)) {
    $data = json_decode(file_get_contents($tempFile), true);
    if (is_array($data) && count($data) > 0) {
        echo "✅ Fixed script is working - found " . count($data) . " records\n";
        unlink($tempFile); // Clean up
    } else {
        echo "❌ Fixed script returned empty data\n";
    }
} else {
    echo "❌ Fixed script test failed\n";
    echo "Output: " . implode("\n", $output) . "\n";
}

echo "\n🎯 READY TO TEST:\n";
echo "=================\n";
echo "Now run: php artisan scrape:trademap --verbose\n";
echo "\nIf it still fails, check these:\n";
echo "1. Laravel logs: tail -f storage/logs/laravel.log\n";
echo "2. Script permissions: ls -la storage/app/fixed_trademap_scraper.cjs\n";
echo "3. Node.js path: which node\n";

?>