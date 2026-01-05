<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Scrapers\TrademapScraper;
use Illuminate\Support\Facades\Log;

class ScrapeTrademapDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        // We can accept parameters here in the future, e.g., years to scrape
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('ScrapeTrademapDataJob started.');

        try {
            $scraper = new TrademapScraper();
            $result = $scraper->execute();

            if ($result['success']) {
                Log::info('ScrapeTrademapDataJob finished successfully.', $result);
            } else {
                Log::error('ScrapeTrademapDataJob failed.', $result);
            }
        } catch (\Exception $e) {
            Log::error('ScrapeTrademapDataJob failed with an exception.', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}