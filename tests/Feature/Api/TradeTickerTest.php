<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\TbTrade;

class TradeTickerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function ticker_calculates_year_over_year_growth_correctly()
    {
        // Setup: Product A grew 50% (100 -> 150)
        TbTrade::factory()->create([
            'kode_hs' => '01', 
            'tahun' => 2023, 
            'jumlah' => 100
        ]);
        TbTrade::factory()->create([
            'kode_hs' => '01', 
            'tahun' => 2024, 
            'jumlah' => 150
        ]);

        // Setup: 2025/2026 empty (should be ignored by ticker)
        TbTrade::factory()->create(['kode_hs' => '01', 'tahun' => 2025, 'jumlah' => 0]);

        $response = $this->get('/api/trade-ticker');

        $response->assertStatus(200);
        $trades = $response->json('trades');

        // Find product 01
        $product = collect($trades)->firstWhere('kode_hs', '01');
        
        $this->assertNotNull($product);
        $this->assertEquals(150, $product['nilai']);
        $this->assertEquals(50.0, $product['change_percent']);
    }

    /** @test */
    public function ticker_handles_zero_previous_year()
    {
        // Setup: New product in 2024
        TbTrade::factory()->create([
            'kode_hs' => '02', 
            'tahun' => 2023, 
            'jumlah' => 0
        ]);
        TbTrade::factory()->create([
            'kode_hs' => '02', 
            'tahun' => 2024, 
            'jumlah' => 100
        ]);

        $response = $this->get('/api/trade-ticker');
        $trades = $response->json('trades');
        $product = collect($trades)->firstWhere('kode_hs', '02');

        $this->assertEquals(0, $product['change_percent']);
    }
}