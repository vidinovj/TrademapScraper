<?php
// debug_laravel_issue.php - Find out why Laravel command fails but test works

echo "🔍 Debugging Laravel vs Test Script Difference\n";
echo "=============================================\n\n";

// Check current TrademapScraper.php
$scraperPath = __DIR__ . '/app/Services/Scrapers/TrademapScraper.php';
if (file_exists($scraperPath)) {
    echo "✅ TrademapScraper.php found\n";
    
    $scraperContent = file_get_contents($scraperPath);
    
    // Check which script it's trying to use
    if (preg_match('/storage\/app\/([^\'\"]+\.cjs)/', $scraperContent, $matches)) {
        $scriptName = $matches[1];
        echo "📄 Laravel scraper is using script: {$scriptName}\n";
        
        $fullScriptPath = __DIR__ . '/storage/app/' . $scriptName;
        if (file_exists($fullScriptPath)) {
            echo "✅ Script file exists at: {$fullScriptPath}\n";
        } else {
            echo "❌ Script file NOT FOUND at: {$fullScriptPath}\n";
            echo "🚨 THIS IS THE PROBLEM!\n";
        }
    } else {
        echo "⚠️  Could not find script path in TrademapScraper.php\n";
    }
    
    // Check if it has debug code that might interfere
    if (strpos($scraperContent, 'DEBUG') !== false) {
        echo "⚠️  TrademapScraper.php still contains debug code\n";
    }
    
} else {
    echo "❌ TrademapScraper.php not found at: {$scraperPath}\n";
}

echo "\n";

// Check what scripts exist in storage/app/
echo "📁 Scripts in storage/app/:\n";
$storageApp = __DIR__ . '/storage/app/';
$scripts = glob($storageApp . '*.cjs');

if (empty($scripts)) {
    echo "❌ NO .cjs scripts found in storage/app/\n";
    echo "🚨 THIS IS THE PROBLEM - The fixed script is missing!\n";
} else {
    foreach ($scripts as $script) {
        $filename = basename($script);
        $size = filesize($script);
        echo "  📄 {$filename} ({$size} bytes)\n";
        
        if ($filename === 'fixed_trademap_scraper.cjs') {
            echo "    ✅ This is the WORKING script\n";
        } elseif ($filename === 'trademap_scraper.cjs') {
            echo "    ❌ This is the OLD broken script\n";
        }
    }
}

echo "\n";

// Check if the working test script exists
$testScript = __DIR__ . '/storage/app/fixed_trademap_scraper.cjs';
echo "🧪 Test script check:\n";
if (file_exists($testScript)) {
    echo "✅ fixed_trademap_scraper.cjs exists\n";
    $testSize = filesize($testScript);
    echo "📊 Size: {$testSize} bytes\n";
    
    // Quick content check
    $testContent = file_get_contents($testScript);
    if (strpos($testContent, 'multi-row header detection') !== false) {
        echo "✅ Contains fixed multi-row header code\n";
    } else {
        echo "❌ Does NOT contain fixed code\n";
    }
} else {
    echo "❌ fixed_trademap_scraper.cjs does NOT exist\n";
    echo "🚨 You need to save the fixed script!\n";
}

echo "\n";

// Show the exact command difference
echo "🔧 Command Comparison:\n";
echo "Working test command:\n";
echo "  node /storage/app/fixed_trademap_scraper.cjs [URL]\n";
echo "\nLaravel command should use:\n";
echo "  node /storage/app/fixed_trademap_scraper.cjs [URL]\n";

echo "\n";

// Check Laravel command file
$commandPath = __DIR__ . '/app/Console/Commands/ScrapeTrademapData.php';
if (file_exists($commandPath)) {
    echo "✅ Laravel command file found\n";
    
    $commandContent = file_get_contents($commandPath);
    if (strpos($commandContent, 'TrademapScraper') !== false) {
        echo "✅ Command uses TrademapScraper class\n";
    } else {
        echo "❌ Command does not use TrademapScraper class\n";
    }
} else {
    echo "❌ Laravel command file not found\n";
}

echo "\n🎯 DIAGNOSIS:\n";
echo "=============\n";

// Most likely issues
$issues = [];

if (!file_exists($testScript)) {
    $issues[] = "The fixed_trademap_scraper.cjs script is missing from storage/app/";
}

if (file_exists($scraperPath)) {
    $scraperContent = file_get_contents($scraperPath);
    if (strpos($scraperContent, 'fixed_trademap_scraper.cjs') === false) {
        $issues[] = "TrademapScraper.php is not using the fixed_trademap_scraper.cjs script";
    }
    if (strpos($scraperContent, 'DEBUG') !== false) {
        $issues[] = "TrademapScraper.php still contains debug code that might interfere";
    }
}

if (empty($issues)) {
    echo "✅ No obvious issues found - check Laravel logs for execution details\n";
} else {
    echo "🚨 FOUND ISSUES:\n";
    foreach ($issues as $i => $issue) {
        echo "  " . ($i + 1) . ". {$issue}\n";
    }
}

echo "\n🔧 SOLUTIONS:\n";
echo "=============\n";

if (!file_exists($testScript)) {
    echo "1. Copy the fixed_trademap_scraper.cjs to storage/app/\n";
    echo "   (The one that works in your test)\n";
}

echo "2. Update TrademapScraper.php to use the correct script:\n";
echo "   \$puppeteerScript = base_path('storage/app/fixed_trademap_scraper.cjs');\n";

echo "3. Remove any debug code from TrademapScraper.php\n";

echo "4. Test the path directly:\n";
echo "   php -r \"echo base_path('storage/app/fixed_trademap_scraper.cjs');\"\n";

?>