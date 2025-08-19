<?php

namespace App\Http\Controllers;

use App\Models\TbTrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TradeDashboardController extends Controller
{
    /**
     * Display the trade data dashboard
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search = $request->get('search', '');
        
        // Get aggregated trade data by HS code with yearly breakdown
        $query = TbTrade::select([
            'kode_hs',
            DB::raw('MAX(label) as product_label'),
            DB::raw('SUM(CASE WHEN tahun = 2020 THEN jumlah ELSE 0 END) as value_2020'),
            DB::raw('SUM(CASE WHEN tahun = 2021 THEN jumlah ELSE 0 END) as value_2021'),
            DB::raw('SUM(CASE WHEN tahun = 2022 THEN jumlah ELSE 0 END) as value_2022'),
            DB::raw('SUM(CASE WHEN tahun = 2023 THEN jumlah ELSE 0 END) as value_2023'),
            DB::raw('SUM(CASE WHEN tahun = 2024 THEN jumlah ELSE 0 END) as value_2024'),
            DB::raw('SUM(jumlah) as total_value')
        ])
        ->groupBy('kode_hs');
        
        // Apply search filter
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('kode_hs', 'LIKE', "%{$search}%")
                  ->orWhere('label', 'LIKE', "%{$search}%");
            });
        }
        
        // Order by total value descending
        $query->orderByDesc('total_value');
        
        $tradeData = $query->paginate($perPage);
        
        // Get summary statistics
        $summaryStats = $this->getSummaryStatistics();
        
        // Get top sectors
        $topSectors = $this->getTopSectors();
        
        return view('dashboard.trade-data', compact(
            'tradeData', 
            'summaryStats', 
            'topSectors',
            'search',
            'perPage'
        ));
    }
    
    /**
     * Get summary statistics for the dashboard
     */
    private function getSummaryStatistics()
    {
        $totalRecords = TbTrade::count();
        $totalValue2024 = TbTrade::where('tahun', 2024)->sum('jumlah');
        $totalHsCodes = TbTrade::distinct('kode_hs')->count();
        $lastUpdate = TbTrade::latest('scraped_at')->first()?->scraped_at;
        
        return [
            'total_records' => $totalRecords,
            'total_value_2024' => $totalValue2024,
            'total_hs_codes' => $totalHsCodes,
            'last_update' => $lastUpdate
        ];
    }
    
    /**
     * Get top trading sectors
     */
    private function getTopSectors()
    {
        return TbTrade::select([
            DB::raw('LEFT(kode_hs, 2) as sector_code'),
            DB::raw('MAX(label) as sector_name'),
            DB::raw('SUM(jumlah) as total_value'),
            DB::raw('COUNT(*) as record_count')
        ])
        ->where('tahun', 2024)
        ->groupBy(DB::raw('LEFT(kode_hs, 2)'))
        ->orderByDesc('total_value')
        ->limit(10)
        ->get();
    }
    
    /**
     * Export trade data to CSV
     */
    public function export(Request $request)
    {
        $search = $request->get('search', '');
        
        $query = TbTrade::select([
            'kode_hs',
            DB::raw('MAX(label) as product_label'),
            DB::raw('SUM(CASE WHEN tahun = 2020 THEN jumlah ELSE 0 END) as value_2020'),
            DB::raw('SUM(CASE WHEN tahun = 2021 THEN jumlah ELSE 0 END) as value_2021'),
            DB::raw('SUM(CASE WHEN tahun = 2022 THEN jumlah ELSE 0 END) as value_2022'),
            DB::raw('SUM(CASE WHEN tahun = 2023 THEN jumlah ELSE 0 END) as value_2023'),
            DB::raw('SUM(CASE WHEN tahun = 2024 THEN jumlah ELSE 0 END) as value_2024')
        ])
        ->groupBy('kode_hs');
        
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('kode_hs', 'LIKE', "%{$search}%")
                  ->orWhere('label', 'LIKE', "%{$search}%");
            });
        }
        
        $data = $query->orderByDesc(DB::raw('SUM(jumlah)'))->get();
        
        $filename = 'indonesia_trade_data_' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];
        
        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'HS Code',
                'Product Label', 
                'Imported Value 2020',
                'Imported Value 2021',
                'Imported Value 2022', 
                'Imported Value 2023',
                'Imported Value 2024'
            ]);
            
            // CSV data
            foreach ($data as $row) {
                fputcsv($file, [
                    $row->kode_hs,
                    $row->product_label,
                    number_format($row->value_2020, 0, '.', ''),
                    number_format($row->value_2021, 0, '.', ''),
                    number_format($row->value_2022, 0, '.', ''),
                    number_format($row->value_2023, 0, '.', ''),
                    number_format($row->value_2024, 0, '.', '')
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
}