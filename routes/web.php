<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TradeDashboardController;

// Redirect root to dashboard
Route::get('/', function () {
    return redirect()->route('dashboard.trade-data');
});



// Trade Data Dashboard Routes
Route::prefix('dashboard')->name('dashboard.')->group(function () {
    
    // Main dashboard
    Route::get('/', [TradeDashboardController::class, 'index'])
        ->name('trade-data');
    
    // Export functionality
    Route::get('/export', [TradeDashboardController::class, 'export'])
        ->name('export');
    
    // Import page
    Route::get('/import', [TradeDashboardController::class, 'showImport'])
        ->name('import');
    
    // CSV validation and import
    Route::post('/validate-csv', [TradeDashboardController::class, 'validateCsv'])
        ->name('validate-csv');
    
    Route::post('/import-csv', [TradeDashboardController::class, 'importCsv'])
        ->name('import-csv');
    
    Route::get('/import-progress/{jobId}', [TradeDashboardController::class, 'getImportProgress'])
        ->name('import-progress');
});