<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\TbTrade;

class TradeDashboardTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function dashboard_anchors_to_latest_year_with_data()
    {
        // Setup: 2024 has data, 2025/2026 are empty
        TbTrade::factory()->create(['tahun' => 2024, 'jumlah' => 1000]);
        TbTrade::factory()->create(['tahun' => 2025, 'jumlah' => 0]);
        TbTrade::factory()->create(['tahun' => 2026, 'jumlah' => 0]);

        $response = $this->get(route('dashboard.trade-data'));

        $response->assertStatus(200);
        
        // Assert view data 'targetYear' is 2024
        $response->assertViewHas('targetYear', 2024);
        
        // Ensure 2024 data is displayed
        $response->assertSee('2024');
    }

    /** @test */
    public function dashboard_defaults_to_2024_if_no_data()
    {
        // No data in DB
        
        $response = $this->get(route('dashboard.trade-data'));

        $response->assertStatus(200);
        $response->assertViewHas('targetYear', 2024);
    }
}