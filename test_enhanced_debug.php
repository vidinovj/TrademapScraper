<?php
// test_enhanced_debug.php
// Quick test for the enhanced debug scraper

require_once __DIR__ . '/vendor/autoload.php';

$testUrl = 'https://www.trademap.org/Product_SelCountry_TS.aspx?nvpm=1%7c360%7c%7c%7c%7cTOTAL%7c%7c%7c2%7c1%7c1%7c1%7c2%7c1%7c1%7c1%7c%7c1';

echo "🔬 Enhanced Debug Test\n";
echo "=====================\n\n";

// Check if enhanced debug script exists
$scriptPath = __DIR__ . '/storage/app/enhanced_debug_trademap_scraper.cjs';
if (!file_exists($scriptPath)) {
    echo "❌ Enhanced debug script not found at: {$scriptPath}\n";
    echo "Please save the enhanced debug script first.\n";
    exit(1);
}
echo "✅ Enhanced debug script found\n\n";

// Run the enhanced debug scraper
echo "4. Running enhanced debug scraper...\n";
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
    
    echo "=== ENHANCED DEBUG OUTPUT ===\n";
    echo $stderr . "\n";
    
    echo "=== TEMP FILE PATH ===\n";
    if (empty($stdout)) {
        echo "❌ No temp file path returned\n";
    } else {
        $tempFile = trim($stdout);
        echo "📁 Temp file: {$tempFile}\n";
        
        if (file_exists($tempFile)) {
            $content = file_get_contents($tempFile);
            $data = json_decode($content, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                echo "✅ Valid JSON with " . count($data) . " records\n";
                
                if (count($data) > 0) {
                    echo "\n🔍 FIRST RECORD ANALYSIS:\n";
                    $firstRecord = $data[0];
                    
                    foreach ($firstRecord as $key => $value) {
                        $valueStr = is_numeric($value) ? $value : '"' . substr($value, 0, 50) . '"';
                        echo "  {$key}: {$valueStr}\n";
                    }
                    
                    echo "\n📊 YEAR VALUES CHECK:\n";
                    $years = [2020, 2021, 2022, 2023, 2024];
                    $hasNonZeroValues = false;
                    
                    foreach ($years as $year) {
                        $yearKey = "value{$year}";
                        $value = $firstRecord[$yearKey] ?? 'NOT_FOUND';
                        echo "  {$yearKey}: {$value}\n";
                        
                        if (is_numeric($value) && $value > 0) {
                            $hasNonZeroValues = true;
                        }
                    }
                    
                    echo "\n🎯 DIAGNOSIS:\n";
                    if ($hasNonZeroValues) {
                        echo "✅ Found non-zero year values! The issue is likely in PHP processing.\n";
                    } else {
                        echo "❌ All year values are 0 or missing. JavaScript column mapping needs fixing.\n";
                    }
                }
            } else {
                echo "❌ Invalid JSON: " . json_last_error_msg() . "\n";
            }
        } else {
            echo "❌ Temp file not found\n";
        }
    }
    
    echo "\nExit code: {$returnValue}\n";
    
} else {
    echo "❌ Failed to execute enhanced debug command\n";
}

echo "\n🔬 Next Steps:\n";
echo "1. Check the enhanced debug output above for detailed column analysis\n";
echo "2. Look for 'FOUND XXXX column at index' messages\n";
echo "3. If year columns are found but values are 0, check the data format\n";
echo "4. If year columns are NOT found, the table structure is different than expected\n";
?>