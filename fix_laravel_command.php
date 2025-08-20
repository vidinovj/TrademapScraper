<?php
// fix_laravel_command.php - Fix the Laravel command to use the right class

echo "🔧 Fixing Laravel Command Class Reference\n";
echo "========================================\n\n";

// Check the Laravel command file
$commandPath = __DIR__ . '/app/Console/Commands/ScrapeTrademapData.php';

if (!file_exists($commandPath)) {
    echo "❌ Laravel command file not found at: {$commandPath}\n";
    exit(1);
}

$commandContent = file_get_contents($commandPath);

echo "📄 Analyzing ScrapeTrademapData.php...\n";

// Check which scraper class it's using
if (preg_match('/use App\\\\Services\\\\Scrapers\\\\([^;]+);/', $commandContent, $matches)) {
    $currentClass = $matches[1];
    echo "  Currently using class: {$currentClass}\n";
    
    if ($currentClass === 'OptimizedTrademapScraper') {
        echo "  🚨 FOUND THE PROBLEM! Using OptimizedTrademapScraper instead of TrademapScraper\n";
    }
} else {
    echo "  ⚠️  Could not find class import\n";
}

// Check instantiation
if (preg_match('/new ([^(]+)\(\)/', $commandContent, $matches)) {
    $instantiatedClass = $matches[1];
    echo "  Instantiating: {$instantiatedClass}\n";
    
    if ($instantiatedClass === 'OptimizedTrademapScraper') {
        echo "  🚨 FOUND THE PROBLEM! Instantiating OptimizedTrademapScraper\n";
    }
}

echo "\n🔧 FIXING the command...\n";

// Fix the import
$commandContent = preg_replace(
    '/use App\\\\Services\\\\Scrapers\\\\OptimizedTrademapScraper;/',
    'use App\\Services\\Scrapers\\TrademapScraper;',
    $commandContent
);

// Fix the instantiation
$commandContent = preg_replace(
    '/new OptimizedTrademapScraper\(\)/',
    'new TrademapScraper()',
    $commandContent
);

// Fix any variable references
$commandContent = preg_replace(
    '/\$scraper = new OptimizedTrademapScraper\(\);/',
    '$scraper = new TrademapScraper();',
    $commandContent
);

// Write the fixed command
file_put_contents($commandPath, $commandContent);
echo "✅ Fixed ScrapeTrademapData.php to use TrademapScraper\n";

echo "\n🗑️  Deleting the problematic OptimizedTrademapScraper.php...\n";

$optimizedScraperPath = __DIR__ . '/app/Services/Scrapers/OptimizedTrademapScraper.php';
if (file_exists($optimizedScraperPath)) {
    // Backup first
    $backupPath = $optimizedScraperPath . '.backup.' . date('Y-m-d-H-i-s');
    copy($optimizedScraperPath, $backupPath);
    
    // Delete the problematic file
    unlink($optimizedScraperPath);
    echo "✅ Deleted OptimizedTrademapScraper.php (backed up as " . basename($backupPath) . ")\n";
} else {
    echo "⚠️  OptimizedTrademapScraper.php not found\n";
}

echo "\n🧹 Clearing autoloader cache...\n";
shell_exec('composer dump-autoload 2>&1');
echo "✅ Composer autoloader refreshed\n";

echo "\n🧪 Testing class loading...\n";
try {
    require_once __DIR__ . '/vendor/autoload.php';
    
    // Test if TrademapScraper can be loaded
    $reflection = new ReflectionClass('App\Services\Scrapers\TrademapScraper');
    echo "✅ TrademapScraper class loads from: " . $reflection->getFileName() . "\n";
    
    // Check if OptimizedTrademapScraper still exists
    try {
        $optimizedReflection = new ReflectionClass('App\Services\Scrapers\OptimizedTrademapScraper');
        echo "⚠️  OptimizedTrademapScraper still exists at: " . $optimizedReflection->getFileName() . "\n";
    } catch (ReflectionException $e) {
        echo "✅ OptimizedTrademapScraper successfully removed\n";
    }
    
} catch (Exception $e) {
    echo "❌ Class loading test failed: " . $e->getMessage() . "\n";
}

echo "\n🎯 NOW RUN:\n";
echo "==========\n";
echo "php artisan config:clear\n";
echo "php artisan cache:clear\n";
echo "php artisan scrape:trademap --verbose\n";
echo "\nYou should NO LONGER see 'DEBUG Final Stats' in the logs!\n";

?>