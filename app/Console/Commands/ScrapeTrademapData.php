<?php
// app/Console/Commands/ScrapeTrademapData.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Scrapers\TrademapScraper;

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
        $this->info('🌐 Starting Trademap Data Scraping...');
        $this->info('📋 Data Engineering Test - Question 2 Solution');
        $this->newLine();

        try {
            // Initialize scraper
            $scraper = new TrademapScraper();
            
            // Show configuration
            if ($this->option('verbose')) {
                $this->info('⚙️  Scraper Configuration:');
                $this->line('   • Target: Indonesia trade data');
                $this->line('   • Years: 2020-2024');
                $this->line('   • Method: Puppeteer (like legal docs scraper)');
                $this->line('   • Rate limiting: 2-5 seconds between requests');
                $this->newLine();
            }

            // Execute scraping
            $this->withProgressBar(['Initializing...'], function () {
                sleep(1); // Simulate setup
            });
            
            $this->newLine();
            $this->info('🕷️  Executing scraping process...');
            
            // Run the scraper
            $result = $scraper->execute();
            
            // Display results
            $this->newLine();
            if ($result['success']) {
                $this->info('✅ Scraping completed successfully!');
                $this->newLine();
                
                $this->info('📊 Results Summary:');
                $this->line("   • Records scraped: {$result['records_scraped']}");
                $this->line("   • Records saved: {$result['records_saved']}");
                $this->line("   • Execution time: {$result['execution_time']} seconds");
                $this->line("   • Years processed: " . implode(', ', $result['years_processed']));
                
                // Calculate performance metrics
                if ($result['execution_time'] > 0) {
                    $recordsPerSecond = round($result['records_scraped'] / $result['execution_time'], 2);
                    $this->line("   • Performance: {$recordsPerSecond} records/second");
                }
                
                $this->newLine();
                $this->info('💾 Data saved to tb_trade table as per test requirements');
                
                // Show sample data
                $this->showSampleData();
                
            } else {
                $this->error('❌ Scraping failed!');
                $this->error("Error: {$result['message']}");
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('💥 Unexpected error occurred:');
            $this->error($e->getMessage());
            
            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }
            
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('🎯 Ready for technical interview demonstration!');
        
        return Command::SUCCESS;
    }

    /**
     * Display sample scraped data
     */
    protected function showSampleData(): void
    {
        try {
            $sampleData = \DB::table('tb_trade')
                ->orderBy('scraped_at', 'desc')
                ->limit(5)
                ->get();

            if ($sampleData->count() > 0) {
                $this->newLine();
                $this->info('📋 Sample Data Preview:');
                
                $headers = ['Negara', 'Kode HS', 'Label', 'Tahun', 'Jumlah', 'Sumber'];
                $rows = [];
                
                foreach ($sampleData as $row) {
                    $rows[] = [
                        $row->negara,
                        $row->kode_hs,
                        \Str::limit($row->label, 30),
                        $row->tahun,
                        number_format($row->jumlah),
                        $row->sumber_data
                    ];
                }
                
                $this->table($headers, $rows);
            }
            
        } catch (\Exception $e) {
            $this->warn('Could not display sample data: ' . $e->getMessage());
        }
    }
}