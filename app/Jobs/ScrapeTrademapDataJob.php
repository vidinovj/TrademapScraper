<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Scrapers\TrademapScraper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ScrapeTrademapDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The product code to scrape.
     */
    public string $productCode;

    /**
     * The unique ID for this job run.
     */
    public string $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $productCode = 'TOTAL', ?string $jobId = null)
    {
        $this->productCode = $productCode;
        $this->jobId = $jobId ?? (string) \Illuminate\Support\Str::uuid();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->updateProgress('running', "Scraping started for product code: {$this->productCode}...");

        try {
            $scraper = new TrademapScraper();
            
            // Here you could add more detailed progress updates from within the scraper if needed
            // For now, we just report start and finish.

            $result = $scraper->execute($this->productCode);

            if ($result['success']) {
                $this->updateProgress('completed', 'Scraping completed successfully.', $result);
                Log::info('ScrapeTrademapDataJob finished successfully.', $result);
            } else {
                $this->updateProgress('failed', $result['message'] ?? 'Scraping failed with no message.', $result);
                Log::error('ScrapeTrademapDataJob failed.', $result);
            }
        } catch (\Exception $e) {
            $this->updateProgress('failed', 'Job failed with an exception: ' . $e->getMessage());
            Log::error('ScrapeTrademapDataJob failed with an exception.', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Re-throw the exception to let the queue manager handle the failure (e.g., move to failed_jobs table)
            throw $e;
        }
    }

    /**
     * Update the progress in the cache.
     */
    protected function updateProgress(string $status, string $message, ?array $result = null): void
    {
        $progress = [
            'status' => $status,
            'message' => $message,
            'job_id' => $this->jobId,
            'product_code' => $this->productCode,
            'updated_at' => now()->toIso8601String(),
            'result' => $result,
        ];

        Cache::put("scrape_progress_{$this->jobId}", $progress, now()->addHours(1));
    }
}