<?php
// app/Http/Controllers/SqlDemoController.php

namespace App\Http\Controllers;

use App\Models\TabelPerdagangan;
use App\Models\TabelNegara;
use App\Models\TabelProduk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SQL Demo Controller for Data Engineer Test Question 1
 * Demonstrates all required SQL queries for technical interview
 */
class SqlDemoController extends Controller
{
    /**
     * Display SQL demonstration page
     */
    public function index()
    {
        $demoData = [
            'question_1a' => $this->getQuestion1aResults(),
            'question_1b' => $this->getQuestion1bResults(),
            'question_1c' => $this->getQuestion1cResults(),
            'optimization_info' => $this->getOptimizationInfo()
        ];

        return view('demo.sql-queries', compact('demoData'));
    }

    /**
     * Question 1a: JOIN query results
     */
    private function getQuestion1aResults()
    {
        $sql = "
            SELECT 
                p.kode_negara AS Kode_Negara,
                n.negara AS Nama_Negara,
                p.hscode AS HsCode,
                pr.label AS Label,
                p.sektor AS Sektor,
                p.bulan AS Bulan,
                p.tahun AS Tahun,
                p.nilai AS Nilai
            FROM tabel_perdagangan p
            INNER JOIN tabel_negara n ON p.kode_negara = n.kode_negara
            INNER JOIN tabel_produk pr ON p.hscode = pr.hscode
            ORDER BY p.kode_negara, p.hscode, p.tahun, p.bulan
        ";

        return [
            'title' => 'Question 1a: JOIN Query',
            'description' => 'Display data with columns: Kode_Negara|Nama_Negara|HsCode|Label|Sektor|Bulan|Tahun|Nilai',
            'sql' => $sql,
            'results' => TabelPerdagangan::getJoinedData()
        ];
    }

    /**
     * Question 1b: GROUP_CONCAT aggregation
     */
    private function getQuestion1bResults()
    {
        $sql = "
            SELECT 
                n.kode_negara AS Kode,
                n.negara AS Nama_Negara,
                GROUP_CONCAT(DISTINCT p.hscode ORDER BY p.hscode SEPARATOR ', ') AS HsCode,
                GROUP_CONCAT(DISTINCT pr.label ORDER BY p.hscode SEPARATOR ', ') AS Label
            FROM tabel_perdagangan p
            INNER JOIN tabel_negara n ON p.kode_negara = n.kode_negara
            INNER JOIN tabel_produk pr ON p.hscode = pr.hscode
            GROUP BY n.kode_negara, n.negara
            ORDER BY n.negara
        ";

        return [
            'title' => 'Question 1b: GROUP_CONCAT Aggregation',
            'description' => 'Group HS codes and labels by country',
            'sql' => $sql,
            'results' => TabelPerdagangan::getAggregatedData()
        ];
    }

    /**
     * Question 1c: Monthly pivot table
     */
    private function getQuestion1cResults()
    {
        $sql = "
            SELECT 
                n.negara AS Negara,
                p.hscode AS 'HS Code',
                pr.label AS Label,
                SUM(CASE WHEN p.bulan = 1 THEN p.nilai ELSE 0 END) AS Januari,
                SUM(CASE WHEN p.bulan = 2 THEN p.nilai ELSE 0 END) AS Febuari,
                SUM(CASE WHEN p.bulan = 3 THEN p.nilai ELSE 0 END) AS Maret,
                SUM(CASE WHEN p.bulan = 4 THEN p.nilai ELSE 0 END) AS April,
                SUM(CASE WHEN p.bulan = 5 THEN p.nilai ELSE 0 END) AS Mei
            FROM tabel_perdagangan p
            INNER JOIN tabel_negara n ON p.kode_negara = n.kode_negara
            INNER JOIN tabel_produk pr ON p.hscode = pr.hscode
            GROUP BY n.negara, p.hscode, pr.label
            ORDER BY n.negara, p.hscode
        ";

        return [
            'title' => 'Question 1c: Monthly Pivot Table',
            'description' => 'Show trade values by month for each country and product',
            'sql' => $sql,
            'results' => TabelPerdagangan::getMonthlyPivot()
        ];
    }

    /**
     * Question 1d: Query optimization information
     */
    private function getOptimizationInfo()
    {
        return [
            'title' => 'Question 1d: Query Optimization Strategies',
            'description' => 'Database optimization techniques implemented',
            'optimizations' => [
                'Primary Keys' => 'All tables have proper primary keys for fastest row access',
                'Foreign Keys' => 'Referential integrity with foreign key constraints',
                'Basic Indexes' => [
                    'idx_negara_kode (kode_negara)',
                    'idx_produk_hscode (hscode)',
                    'idx_perdagangan_negara (kode_negara)',
                    'idx_perdagangan_hscode (hscode)',
                    'idx_perdagangan_tahun (tahun)',
                    'idx_perdagangan_bulan (bulan)'
                ],
                'Composite Indexes' => [
                    'idx_perdagangan_negara_tahun (kode_negara, tahun)',
                    'idx_perdagangan_hscode_bulan (hscode, bulan)',
                    'idx_perdagangan_sektor_tahun (sektor, tahun)'
                ],
                'Additional Strategies' => [
                    'Table partitioning by year for large datasets',
                    'Query hints for specific optimization',
                    'Materialized views for complex reporting',
                    'Archive strategy for historical data',
                    'Denormalization for frequently accessed data'
                ]
            ]
        ];
    }

    /**
     * API endpoint for SQL query results (for AJAX calls)
     */
    public function getQueryResults(Request $request)
    {
        $queryType = $request->get('type');
        
        switch ($queryType) {
            case '1a':
                return response()->json($this->getQuestion1aResults());
            case '1b':
                return response()->json($this->getQuestion1bResults());
            case '1c':
                return response()->json($this->getQuestion1cResults());
            case 'optimization':
                return response()->json($this->getOptimizationInfo());
            default:
                return response()->json(['error' => 'Invalid query type'], 400);
        }
    }

    /**
     * Execute raw SQL for demonstration purposes
     */
    public function executeRawSql(Request $request)
    {
        $sqlQuery = $request->get('sql');
        
        try {
            $results = DB::select($sqlQuery);
            return response()->json([
                'success' => true,
                'results' => $results,
                'count' => count($results)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Download results as CSV for verification
     */
    public function downloadResults($queryType)
    {
        $data = null;
        $filename = '';
        
        switch ($queryType) {
            case '1a':
                $data = TabelPerdagangan::getJoinedData();
                $filename = 'question_1a_join_results.csv';
                break;
            case '1b':
                $data = TabelPerdagangan::getAggregatedData();
                $filename = 'question_1b_groupconcat_results.csv';
                break;
            case '1c':
                $data = TabelPerdagangan::getMonthlyPivot();
                $filename = 'question_1c_monthly_pivot_results.csv';
                break;
            default:
                abort(404);
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Write headers
            if ($data->count() > 0) {
                fputcsv($file, array_keys((array)$data->first()));
                
                // Write data
                foreach ($data as $row) {
                    fputcsv($file, (array)$row);
                }
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

// Add these routes to routes/web.php:
/*
Route::prefix('sql-demo')->name('sql-demo.')->group(function () {
    Route::get('/', [SqlDemoController::class, 'index'])->name('index');
    Route::get('/query/{type}', [SqlDemoController::class, 'getQueryResults'])->name('query');
    Route::post('/execute', [SqlDemoController::class, 'executeRawSql'])->name('execute');
    Route::get('/download/{type}', [SqlDemoController::class, 'downloadResults'])->name('download');
});
*/