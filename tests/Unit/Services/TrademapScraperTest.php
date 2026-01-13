<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\Scrapers\TrademapScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\TbTrade;

class TrademapScraperTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_processes_valid_json_data_correctly()
    {
        $scraper = new TestableTrademapScraper();
        
        // Mock data returning 2024 and 2023 values
        $mockData = json_encode([
            [
                'hsCode' => '10',
                'productLabel' => 'Cereals',
                'value2024' => 5000,
                'value2023' => 4000
            ]
        ]);
        
        $scraper->setMockJson($mockData);

        $result = $scraper->execute('10');

        $this->assertTrue($result['success']);
        // 1 input record * 5 years (2022-2026) = 5 processed records
        $this->assertEquals(5, $result['records_scraped']);
        
        // Check DB
        $this->assertDatabaseHas('tb_trade', [
            'kode_hs' => '10',
            'tahun' => 2024,
            'jumlah' => 5000
        ]);
        
        $this->assertDatabaseHas('tb_trade', [
            'kode_hs' => '10',
            'tahun' => 2023,
            'jumlah' => 4000
        ]);
        
        // Verify it inserted 0 for missing years in the mock
        $this->assertDatabaseHas('tb_trade', [
            'kode_hs' => '10',
            'tahun' => 2025,
            'jumlah' => 0
        ]);
    }

    /** @test */
    public function it_skips_invalid_records()
    {
        $scraper = new TestableTrademapScraper();
        
        $mockData = json_encode([
            [
                'hsCode' => '1', 
                'productLabel' => '2', // Invalid pattern
                'value2024' => 100
            ],
            [
                'hsCode' => '85',
                'productLabel' => 'Electronics',
                'value2024' => 200
            ]
        ]);
        
        $scraper->setMockJson($mockData);
        $result = $scraper->execute('TOTAL');
        
        // Only 1 record should be valid * 5 years = 5 records
        $this->assertEquals(5, $result['records_saved']);
        
        $this->assertDatabaseMissing('tb_trade', ['kode_hs' => '1']);
        $this->assertDatabaseHas('tb_trade', ['kode_hs' => '85']);
    }
}

class TestableTrademapScraper extends TrademapScraper
{
    protected ?string $mockJson = null;

    public function setMockJson(string $json)
    {
        $this->mockJson = $json;
    }

    protected function executePuppeteerScraping(string $url): ?string
    {
        return $this->mockJson;
    }
}
