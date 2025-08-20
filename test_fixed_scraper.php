<?php
// test_fixed_scraper.php
// Test the FIXED scraper that handles multi-row headers correctly

require_once __DIR__ . '/vendor/autoload.php';

$testUrl = 'https://www.trademap.org/Product_SelCountry_TS.aspx?nvpm=1%7c360%7c%7c%7c%7cTOTAL%7c%7c%7c2%7c1%7c1%7c1%7c2%7c1%7c1%7c1%7c%7c1';

echo "🎯 Testing FIXED Trademap Scraper\n";
echo "=================================\n\n";

// Check if fixed script exists
$scriptPath = __DIR__ . '/storage/app/fixed_trademap_scraper.cjs';
if (!file_exists($scriptPath)) {
    echo "❌ Fixed scraper script not found at: {$scriptPath}\n";
    echo "Please save the fixed script first.\n";
    exit(1);
}
echo "✅ Fixed scraper script found\n\n";

// Run the fixed scraper
echo "🚀 Running FIXED scraper...\n";
echo "URL: {$testUrl}\n\n";

$command = "node {$scriptPath} " . escapeshellarg($testUrl);
echo "Executing: {$command}\n\n";

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
    
    echo "=== FIXED SCRAPER DEBUG OUTPUT ===\n";
    echo $stderr . "\n";
    
    echo "=== RESULTS ===\n";
    if (empty($stdout)) {
        echo "❌ No temp file path returned\n";
    } else {
        $tempFile = trim($stdout);
        echo "📁 Temp file: {$tempFile}\n";
        
        if (file_exists($tempFile)) {
            $content = file_get_contents($tempFile);
            $data = json_decode($content, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                echo "✅ Valid JSON with " . count($data) . " records\n\n";
                
                if (count($data) > 0) {
                    echo "🎉 SUCCESS! Found " . count($data) . " records\n\n";
                    
                    echo "📋 FIRST 3 RECORDS:\n";
                    echo "==================\n";
                    
                    for ($i = 0; $i < min(3, count($data)); $i++) {
                        $record = $data[$i];
                        echo "Record " . ($i + 1) . ":\n";
                        echo "  HS Code: " . ($record['hsCode'] ?? 'NULL') . "\n";
                        echo "  Product: " . substr($record['productLabel'] ?? 'NULL', 0, 60) . "...\n";
                        echo "  2020: " . number_format($record['value2020'] ?? 0) . "\n";
                        echo "  2021: " . number_format($record['value2021'] ?? 0) . "\n";
                        echo "  2022: " . number_format($record['value2022'] ?? 0) . "\n";
                        echo "  2023: " . number_format($record['value2023'] ?? 0) . "\n";
                        echo "  2024: " . number_format($record['value2024'] ?? 0) . "\n\n";
                    }
                    
                    // Check for non-zero values
                    $nonZeroCount = 0;
                    $totalValue = 0;
                    
                    foreach ($data as $record) {
                        $years = [2020, 2021, 2022, 2023, 2024];
                        foreach ($years as $year) {
                            $value = $record["value{$year}"] ?? 0;
                            if ($value > 0) {
                                $nonZeroCount++;
                                $totalValue += $value;
                            }
                        }
                    }
                    
                    echo "📊 DATA QUALITY CHECK:\n";
                    echo "======================\n";
                    echo "  Records with data: " . count($data) . "\n";
                    echo "  Non-zero values: {$nonZeroCount}\n";
                    echo "  Total trade value: $" . number_format($totalValue) . " thousand\n";
                    
                    if ($nonZeroCount > 0) {
                        echo "\n🎯 RESULT: SUCCESS! The fixed scraper is working!\n";
                        echo "This data should now process correctly in your PHP scraper.\n";
                    } else {
                        echo "\n⚠️  WARNING: All values are zero - check number parsing\n";
                    }
                    
                } else {
                    echo "❌ No records found - something is still wrong\n";
                }
                
            } else {
                echo "❌ Invalid JSON: " . json_last_error_msg() . "\n";
                echo "Raw content: " . substr($content, 0, 500) . "\n";
            }
        } else {
            echo "❌ Temp file not found\n";
        }
    }
    
    echo "\nExit code: {$returnValue}\n";
    
} else {
    echo "❌ Failed to execute fixed scraper command\n";
}

echo "\n🎯 Next Steps:\n";
echo "1. If SUCCESS: Update your TrademapScraper.php to use fixed_trademap_scraper.cjs\n";
echo "2. If still failing: Check the debug output above for clues\n";
echo "3. The fixed script targets the exact table structure we found in debugging\n";
?>