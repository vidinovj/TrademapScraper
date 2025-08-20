<?php
// test_session_debug.php
// Test the session-aware debug scraper

require_once __DIR__ . '/vendor/autoload.php';

$testUrl = 'https://www.trademap.org/Product_SelCountry_TS.aspx?nvpm=1%7c360%7c%7c%7c%7cTOTAL%7c%7c%7c2%7c1%7c1%7c1%7c2%7c1%7c1%7c1%7c%7c1';

echo "🔬 Session-Aware Debug Test\n";
echo "===========================\n\n";

// Check if session debug script exists
$scriptPath = __DIR__ . '/storage/app/session_aware_debug_scraper.cjs';
if (!file_exists($scriptPath)) {
    echo "❌ Session debug script not found at: {$scriptPath}\n";
    echo "Please save the session-aware debug script first.\n";
    exit(1);
}
echo "✅ Session debug script found\n\n";

// Clean up old screenshots
$oldScreenshots = glob('/tmp/trademap_*.png');
foreach ($oldScreenshots as $screenshot) {
    unlink($screenshot);
}

// Run the session-aware debug scraper
echo "🔄 Running session-aware debug scraper...\n";
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
    
    echo "=== SESSION DEBUG OUTPUT ===\n";
    echo $stderr . "\n";
    
    echo "=== ANALYSIS FILE ===\n";
    if (empty($stdout)) {
        echo "❌ No analysis file path returned\n";
    } else {
        $tempFile = trim($stdout);
        echo "📁 Analysis file: {$tempFile}\n";
        
        if (file_exists($tempFile)) {
            $content = file_get_contents($tempFile);
            $data = json_decode($content, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                echo "✅ Valid analysis with " . count($data) . " tables\n";
                
                // Show key findings
                echo "\n🔍 KEY FINDINGS:\n";
                $dataTablesFound = 0;
                $tablesWithYears = 0;
                $tablesWithNumbers = 0;
                
                foreach ($data as $table) {
                    if ($table['hasYearColumns']) $tablesWithYears++;
                    if ($table['hasNumericData']) $tablesWithNumbers++;
                    if ($table['hasYearColumns'] && $table['hasNumericData'] && $table['totalRows'] > 5) {
                        $dataTablesFound++;
                    }
                }
                
                echo "  📊 Tables with year columns: {$tablesWithYears}\n";
                echo "  🔢 Tables with numeric data: {$tablesWithNumbers}\n";
                echo "  ✅ Potential data tables: {$dataTablesFound}\n";
                
                if ($dataTablesFound > 0) {
                    echo "\n🎯 SOLUTION: Data tables found! The original script needs adjustment.\n";
                } else {
                    echo "\n🚨 ISSUE: No data tables found. Likely authentication or dynamic loading issue.\n";
                }
                
            } else {
                echo "❌ Invalid JSON: " . json_last_error_msg() . "\n";
            }
        } else {
            echo "❌ Analysis file not found\n";
        }
    }
    
    echo "\n📸 SCREENSHOTS:\n";
    $screenshots = glob('/tmp/trademap_*.png');
    if (empty($screenshots)) {
        echo "❌ No screenshots found\n";
    } else {
        foreach ($screenshots as $screenshot) {
            $filename = basename($screenshot);
            $size = filesize($screenshot);
            echo "  📸 {$filename} ({$size} bytes)\n";
        }
        echo "\n💡 Open these images to see what the page actually looks like!\n";
    }
    
    echo "\nExit code: {$returnValue}\n";
    
} else {
    echo "❌ Failed to execute session debug command\n";
}

echo "\n🔬 Next Steps Based on Results:\n";
echo "1. Check screenshots to see if page loaded correctly\n";
echo "2. If login required: Need to handle authentication\n";
echo "3. If data tables found: Fix column mapping in original script\n";
echo "4. If no data: Try different URL or approach\n";
?>