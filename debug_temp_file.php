<?php
// debug_temp_file.php
// Quick script to inspect the temp file contents

// Get the latest temp file
$tempFiles = glob('/tmp/trademap_all_years_*.json');
if (empty($tempFiles)) {
    echo "❌ No temp files found in /tmp/\n";
    exit(1);
}

// Sort by modification time and get the latest
usort($tempFiles, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

$latestFile = $tempFiles[0];
echo "🔍 Inspecting latest temp file: {$latestFile}\n\n";

if (!file_exists($latestFile)) {
    echo "❌ File does not exist\n";
    exit(1);
}

$content = file_get_contents($latestFile);
if (empty($content)) {
    echo "❌ File is empty\n";
    exit(1);
}

$data = json_decode($content, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ Invalid JSON: " . json_last_error_msg() . "\n";
    echo "Raw content (first 500 chars):\n";
    echo substr($content, 0, 500) . "\n";
    exit(1);
}

echo "✅ Valid JSON found\n";
echo "📊 Total records: " . count($data) . "\n\n";

if (count($data) > 0) {
    echo "🔍 FIRST RECORD STRUCTURE:\n";
    echo "==========================================\n";
    $firstRecord = $data[0];
    
    foreach ($firstRecord as $key => $value) {
        $valueStr = is_array($value) ? json_encode($value) : (string)$value;
        $valueStr = strlen($valueStr) > 100 ? substr($valueStr, 0, 100) . '...' : $valueStr;
        echo sprintf("%-15s: %s\n", $key, $valueStr);
    }
    
    echo "\n🔍 YEAR VALUES CHECK:\n";
    echo "==========================================\n";
    $years = [2020, 2021, 2022, 2023, 2024];
    
    foreach ($years as $year) {
        $yearKey = "value{$year}";
        $value = $firstRecord[$yearKey] ?? 'NOT_FOUND';
        echo sprintf("%-10s: %s\n", $yearKey, $value);
    }
    
    echo "\n🔍 ALL RECORDS SUMMARY:\n";
    echo "==========================================\n";
    
    $stats = [
        'has_hsCode' => 0,
        'has_productLabel' => 0,
        'has_non_zero_values' => 0
    ];
    
    foreach ($data as $index => $record) {
        if (!empty($record['hsCode'])) $stats['has_hsCode']++;
        if (!empty($record['productLabel'])) $stats['has_productLabel']++;
        
        $hasNonZeroValue = false;
        foreach ($years as $year) {
            $yearKey = "value{$year}";
            $value = $record[$yearKey] ?? 0;
            if (is_numeric($value) && $value > 0) {
                $hasNonZeroValue = true;
                break;
            }
        }
        if ($hasNonZeroValue) $stats['has_non_zero_values']++;
        
        // Show first 5 records
        if ($index < 5) {
            echo "\nRecord {$index}:\n";
            echo "  hsCode: " . ($record['hsCode'] ?? 'NULL') . "\n";
            echo "  productLabel: " . substr($record['productLabel'] ?? 'NULL', 0, 50) . "...\n";
            foreach ($years as $year) {
                $yearKey = "value{$year}";
                $value = $record[$yearKey] ?? 'NULL';
                echo "  {$yearKey}: {$value}\n";
            }
        }
    }
    
    echo "\n📈 STATISTICS:\n";
    echo "==========================================\n";
    foreach ($stats as $key => $count) {
        $percentage = round(($count / count($data)) * 100, 1);
        echo sprintf("%-20s: %d/%d (%s%%)\n", $key, $count, count($data), $percentage);
    }
    
    echo "\n🎯 DIAGNOSIS:\n";
    echo "==========================================\n";
    
    if ($stats['has_hsCode'] === 0) {
        echo "❌ NO HS CODES found - JavaScript may be extracting wrong column\n";
    }
    
    if ($stats['has_productLabel'] === 0) {
        echo "❌ NO PRODUCT LABELS found - JavaScript may be extracting wrong column\n";
    }
    
    if ($stats['has_non_zero_values'] === 0) {
        echo "❌ NO NON-ZERO VALUES found - All year values are 0 or empty\n";
        echo "   This could mean:\n";
        echo "   1. JavaScript is extracting wrong columns for year data\n";
        echo "   2. The data format is not being parsed correctly\n";
        echo "   3. The table structure is different than expected\n";
    } else {
        echo "✅ Found {$stats['has_non_zero_values']} records with non-zero values\n";
    }
}

echo "\n🔧 RECOMMENDATIONS:\n";
echo "==========================================\n";
echo "1. Run this script to see the exact data structure\n";
echo "2. Update the PHP debug version to see processing details\n";
echo "3. Check if the JavaScript column mapping is correct\n";
echo "4. Verify the number format parsing in extractNumericValue()\n";
?>