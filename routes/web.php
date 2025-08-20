<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TradeDashboardController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

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
    
    // NEW: Import page and functionality
    Route::get('/import', [TradeDashboardController::class, 'showImport'])
        ->name('import');
    
    // CSV Import functionality (addresses Data Engineer Test Question 3 & 4)
    Route::post('/import-csv', [TradeDashboardController::class, 'importCsv'])
        ->name('import-csv');
    
    // Get import progress (for AJAX progress tracking)
    Route::get('/import-progress/{jobId}', [TradeDashboardController::class, 'getImportProgress'])
        ->name('import-progress');
    
    // Validate CSV before import
    Route::post('/validate-csv', [TradeDashboardController::class, 'validateCsv'])
        ->name('validate-csv');
});