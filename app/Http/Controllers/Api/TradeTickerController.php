<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TbTrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class TradeTickerController extends Controller
{
    /**
     * Get latest trade data for ticker display
     */
    public function getLatestTradeData(Request $request)
    {
        try {
            // Cache for 30 seconds to avoid excessive database queries
            $trades = Cache::remember('ticker_trade_data', 30, function () {
                return $this->fetchLatestTrades();
            });
            
            return response()->json([
                'success' => true,
                'trades' => $trades,
                'timestamp' => now()->toISOString(),
                'count' => count($trades)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch trade data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Fetch latest trades with calculated changes
     */
    private function fetchLatestTrades()
    {
        // 1. Determine the latest year that actually has data (anchored to reality)
        // We look for the max year where the sum of trade values is > 0
        $latestYear = TbTrade::where('jumlah', '>', 0)->max('tahun') ?? 2024;
        $previousYear = $latestYear - 1;

        // 2. Fetch top trades for this specific year
        // We focus on the highest value items to make the ticker interesting
        $trades = TbTrade::select([
                'kode_hs',
                'label',
                'jumlah as nilai',
                'tahun',
                'scraped_at'
            ])
            ->where('tahun', $latestYear)
            ->where('jumlah', '>', 0) // Ensure we don't pick up empty placeholders
            ->orderBy('jumlah', 'desc')
            ->limit(15)
            ->get();

        if ($trades->isEmpty()) {
            return [];
        }

        // 3. Calculate percentage changes by looking up the previous year's data
        return $trades->map(function ($trade) use ($previousYear) {
            
            $previousValue = TbTrade::where('kode_hs', $trade->kode_hs)
                ->where('tahun', $previousYear)
                ->value('jumlah');

            $changePercent = 0;
            
            if ($previousValue && $previousValue > 0) {
                $changePercent = (($trade->nilai - $previousValue) / $previousValue) * 100;
            } else {
                // If no previous data, we can't calculate a real change. 
                // Return 0 or maybe a small random indicator if we want to simulate liveliness (optional),
                // but 0 is safer for accuracy.
                $changePercent = 0; 
            }

            return [
                'kode_hs' => $trade->kode_hs,
                'label' => $trade->label,
                'nilai' => $trade->nilai,
                'change_percent' => round($changePercent, 1),
                'scraped_at' => Carbon::parse($trade->scraped_at)->format('H:i')
            ];
        })->toArray();
    }
    
    /**
     * Calculate percentage change for a specific HS code
     * (Deprecated/Unused in new logic but kept if needed for individual lookups)
     */
    private function calculateChangePercent($hsCode)
    {
        // Logic moved inside fetchLatestTrades for batch efficiency
        return 0;
    }
    
    /**
     * Get ticker statistics summary
     */
    public function getTickerSummary()
    {
        try {
            $summary = Cache::remember('ticker_summary', 60, function () {
                $today = now()->toDateString();
                
                return [
                    'total_records_today' => TbTrade::whereDate('scraped_at', $today)->count(),
                    'total_value_today' => TbTrade::whereDate('scraped_at', $today)->sum('jumlah'),
                    'unique_hs_codes_today' => TbTrade::whereDate('scraped_at', $today)->distinct('kode_hs')->count(),
                    'last_update' => TbTrade::latest('scraped_at')->first()?->scraped_at,
                    'trending_sector' => $this->getTrendingSector()
                ];
            });
            
            return response()->json([
                'success' => true,
                'summary' => $summary
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch summary data'
            ], 500);
        }
    }
    
    /**
     * Get trending sector based on recent activity
     */
    private function getTrendingSector()
    {
        $trendingSector = TbTrade::select([
            DB::raw('LEFT(kode_hs, 2) as sector_code'),
            DB::raw('COUNT(*) as activity_count'),
            DB::raw('MAX(label) as sample_label')
        ])
        ->whereDate('scraped_at', '>=', now()->subDays(7))
        ->groupBy(DB::raw('LEFT(kode_hs, 2)'))
        ->orderByDesc('activity_count')
        ->first();
        
        return $trendingSector ? [
            'code' => $trendingSector->sector_code,
            'name' => $this->getSectorName($trendingSector->sector_code),
            'activity_count' => $trendingSector->activity_count
        ] : null;
    }
    
    /**
     * Get sector name from HS code
     */
    private function getSectorName($sectorCode)
    {
        $sectorNames = [
            '01' => 'Hewan Hidup',
            '02' => 'Daging',
            '03' => 'Ikan dan Udang',
            '27' => 'Bahan Bakar Mineral',
            '84' => 'Mesin dan Peralatan',
            '85' => 'Peralatan Elektrik',
            '72' => 'Besi dan Baja',
            '39' => 'Plastik',
            '87' => 'Kendaraan',
            '29' => 'Bahan Kimia Organik'
        ];
        
        return $sectorNames[$sectorCode] ?? "Sektor {$sectorCode}";
    }
    
    /**
     * Force refresh ticker data (clear cache)
     */
    public function refreshTicker()
    {
        Cache::forget('ticker_trade_data');
        Cache::forget('ticker_summary');
        
        return response()->json([
            'success' => true,
            'message' => 'Ticker data refreshed'
        ]);
    }
    
    /**
     * Get ticker configuration
     */
    public function getTickerConfig()
    {
        return response()->json([
            'success' => true,
            'config' => [
                'update_interval' => 30000, // 30 seconds
                'animation_speed' => 'normal',
                'max_items' => 15,
                'show_changes' => true,
                'auto_refresh' => true
            ]
        ]);
    }
}