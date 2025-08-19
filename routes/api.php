<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TradeTickerController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Ticker API endpoints
Route::prefix('ticker')->name('api.ticker.')->group(function () {
    // Get latest trade data for ticker
    Route::get('/latest', [TradeTickerController::class, 'getLatestTradeData'])
        ->name('latest');
    
    // Get ticker summary statistics
    Route::get('/summary', [TradeTickerController::class, 'getTickerSummary'])
        ->name('summary');
    
    // Force refresh ticker data
    Route::post('/refresh', [TradeTickerController::class, 'refreshTicker'])
        ->name('refresh');
    
    // Get ticker configuration
    Route::get('/config', [TradeTickerController::class, 'getTickerConfig'])
        ->name('config');
});

// Legacy endpoint for backward compatibility
Route::get('/trade-data-latest', [TradeTickerController::class, 'getLatestTradeData'])
    ->name('api.trade-data-latest');

// Get trade data as JSON (for AJAX)
Route::get('/trade-data', function () {
    return response()->json([
        'data' => \App\Models\TbTrade::latest()->limit(10)->get(),
        'summary' => \App\Models\TbTrade::getSummaryStats()
    ]);
})->name('api.trade-data');

// Get specific HS code details
Route::get('/hs-code/{code}', function ($code) {
    $data = \App\Models\TbTrade::where('kode_hs', $code)
        ->select([
            'kode_hs',
            'label',
            'tahun',
            'jumlah',
            'scraped_at'
        ])
        ->orderBy('tahun')
        ->get();
        
    return response()->json($data);
})->name('api.hs-code-detail');
