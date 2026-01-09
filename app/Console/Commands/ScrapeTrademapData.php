<?php
// app/Console/Commands/ScrapeTrademapData.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ScrapeTrademapDataJob;
use App\Models\TbTrade;
use Illuminate\Support\Facades\DB;

class ScrapeTrademapData extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'scrape:trademap 
                            {--productCode= : The product code to start scraping from (e.g., 27 for mineral fuels)}
                            {--drill-down : Find all HS2 codes and dispatch jobs to scrape their children}
                            {--years=* : Specific years to scrape (default: 2020-2024)}
                            {--dry-run : Run without saving to database}';

    /**
     * The console command description.
     */
    protected $description = 'Scrape Indonesia trade data from Trademap.org (Data Engineering Test)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('drill-down')) {
            return $this->handleDrillDown();
        }

        $productCode = $this->option('productCode') ?? 'TOTAL';
        $this->info("🌐 Dispatching Trademap Data Scraping Job for product code: {$productCode}...");

        try {
            ScrapeTrademapDataJob::dispatch($productCode);
            $this->info('✅ Job dispatched successfully!');
            $this->info('The scraping process will run in the background.');
            $this->info('Run "php artisan queue:work" to process the queue.');
        } catch (\Exception $e) {
            $this->error('💥 Failed to dispatch job:');
            $this->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Handle the drill-down logic.
     */
    protected function handleDrillDown(): int
    {
        $this->info(' drilling down mode activated. Finding all HS2 codes to scrape...');
        
        try {
            $hs2Codes = TbTrade::select('kode_hs')
                                ->where(DB::raw("LENGTH(REPLACE(kode_hs, '.', ''))"), 2)
                                ->distinct()
                                ->pluck('kode_hs');

            if ($hs2Codes->isEmpty()) {
                $this->warn('No HS2 codes found in the database to drill down from. Scrape with --productCode=TOTAL first.');
                return Command::SUCCESS;
            }

            $this->info("Found {$hs2Codes->count()} HS2 codes to process.");
            $this->withProgressBar($hs2Codes, function ($code) {
                ScrapeTrademapDataJob::dispatch($code);
            });
            $this->info(''); // Newline after progress bar

            $this->info('✅ All drill-down jobs dispatched successfully!');
            $this->info('Run "php artisan queue:work" to process the queue.');

        } catch (\Exception $e) {
            $this->error('💥 Failed to dispatch drill-down jobs:');
            $this->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}