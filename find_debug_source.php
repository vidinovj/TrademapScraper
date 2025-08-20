<?php
// find_debug_source.php - Find where the DEBUG code is actually coming from

echo "🔍 Finding the Source of DEBUG Code\n";
echo "===================================\n\n";

// Search for DEBUG Final Stats in all PHP files
function searchForDebugCode($directory) {
    $found = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory)
    );
    
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $content = file_get_contents($file->getRealPath());
            
            // Look for the specific DEBUG output we're seeing
            if (strpos($content, 'DEBUG Final Stats') !== false ||
                strpos($content, 'year_values_found') !== false ||
                strpos($content, 'valid_hs_codes') !== false) {
                
                $found[] = [
                    'file' => $file->getRealPath(),
                    'size' => $file->getSize(),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime())
                ];
            }
        }
    }
    
    return $found;
}

echo "🔍 Searching for DEBUG code in app/ directory...\n";
$debugFiles = searchForDebugCode(__DIR__ . '/app');

if (empty($debugFiles)) {
    echo "✅ No DEBUG code found in app/ directory\n";
} else {
    echo "🚨 FOUND DEBUG CODE in these files:\n";
    foreach ($debugFiles as $file) {
        echo "  📄 {$file['file']}\n";
        echo "     Size: {$file['size']} bytes, Modified: {$file['modified']}\n";
        
        // Show the specific debug content
        $content = file_get_contents($file['file']);
        if (preg_match('/DEBUG Final Stats.*?\}/s', $content, $matches)) {
            echo "     Contains: " . substr($matches[0], 0, 100) . "...\n";
        }
        echo "\n";
    }
}

echo "\n🔍 Checking all possible TrademapScraper files...\n";

// Find ALL files named TrademapScraper.php
$scraperFiles = [];
$allFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__)
);

foreach ($allFiles as $file) {
    if ($file->getFilename() === 'TrademapScraper.php') {
        $scraperFiles[] = $file->getRealPath();
    }
}

echo "Found " . count($scraperFiles) . " TrademapScraper.php files:\n";
foreach ($scraperFiles as $scraperFile) {
    echo "  📄 {$scraperFile}\n";
    
    $content = file_get_contents($scraperFile);
    $hasDebug = strpos($content, 'DEBUG') !== false;
    $size = filesize($scraperFile);
    $modified = date('Y-m-d H:i:s', filemtime($scraperFile));
    
    echo "     Size: {$size} bytes, Modified: {$modified}\n";
    echo "     Has DEBUG: " . ($hasDebug ? "❌ YES" : "✅ NO") . "\n";
    
    if ($hasDebug) {
        echo "     🚨 THIS FILE HAS DEBUG CODE!\n";
    }
    echo "\n";
}

echo "\n🔍 Checking composer autoloader...\n";

// Check if composer has cached the old class
$composerFiles = [
    __DIR__ . '/vendor/composer/autoload_classmap.php',
    __DIR__ . '/vendor/composer/autoload_psr4.php'
];

foreach ($composerFiles as $composerFile) {
    if (file_exists($composerFile)) {
        echo "📄 Checking {$composerFile}\n";
        $content = file_get_contents($composerFile);
        if (strpos($content, 'TrademapScraper') !== false) {
            echo "  ✅ Contains TrademapScraper reference\n";
        } else {
            echo "  ⚠️  No TrademapScraper reference found\n";
        }
    }
}

echo "\n🔍 Checking Laravel cache files...\n";

$cacheDirectories = [
    __DIR__ . '/bootstrap/cache',
    __DIR__ . '/storage/framework/cache',
    __DIR__ . '/storage/framework/views'
];

foreach ($cacheDirectories as $cacheDir) {
    if (is_dir($cacheDir)) {
        $cacheFiles = glob($cacheDir . '/*');
        echo "📁 {$cacheDir}: " . count($cacheFiles) . " files\n";
        
        foreach ($cacheFiles as $cacheFile) {
            if (is_file($cacheFile)) {
                $content = file_get_contents($cacheFile);
                if (strpos($content, 'DEBUG Final Stats') !== false) {
                    echo "  🚨 FOUND DEBUG CODE IN CACHE: " . basename($cacheFile) . "\n";
                }
            }
        }
    } else {
        echo "📁 {$cacheDir}: Directory not found\n";
    }
}

echo "\n🔍 Testing class loading...\n";

try {
    // Test if we can load the class and see which file it comes from
    $reflection = new ReflectionClass('App\Services\Scrapers\TrademapScraper');
    $filename = $reflection->getFileName();
    echo "✅ TrademapScraper class loads from: {$filename}\n";
    
    $content = file_get_contents($filename);
    $hasDebug = strpos($content, 'DEBUG') !== false;
    echo "   Has DEBUG code: " . ($hasDebug ? "❌ YES" : "✅ NO") . "\n";
    
    if ($hasDebug) {
        echo "   🚨 THE LOADED CLASS STILL HAS DEBUG CODE!\n";
        echo "   This means Laravel is loading the wrong file or there's caching.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Could not load TrademapScraper class: " . $e->getMessage() . "\n";
}

echo "\n🎯 SOLUTIONS TO TRY:\n";
echo "===================\n";
echo "1. Clear ALL Laravel caches:\n";
echo "   php artisan config:clear\n";
echo "   php artisan cache:clear\n";
echo "   php artisan route:clear\n";
echo "   php artisan view:clear\n";
echo "\n";
echo "2. Clear Composer autoloader:\n";
echo "   composer dump-autoload\n";
echo "\n";
echo "3. Restart your web server/PHP-FPM if running\n";
echo "\n";
echo "4. Check if there are multiple TrademapScraper files\n";

?>