<?php
// app/Console/Commands/ScrapeTrademapData.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ScrapeTrademapDataJob;

class ScrapeTrademapData extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'scrape:trademap 
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
        $this->info('🌐 Dispatching Trademap Data Scraping Job...');

        try {
            ScrapeTrademapDataJob::dispatch();
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


}