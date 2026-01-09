<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JobDispatchController;
use App\Http\Controllers\TradeDashboardController;

Route::get('/', function () {
    return view('welcome');
});

// Trade Data Dashboard Routes
Route::prefix('dashboard')->name('dashboard.')->group(function () {
    
    // Main dashboard
    Route::get('/', [TradeDashboardController::class, 'index'])
        ->name('trade-data');
    
    // Jobs page and actions
    Route::get('/jobs', [TradeDashboardController::class, 'showJobsPage'])
        ->name('jobs');
    Route::post('/jobs/scrape', [JobDispatchController::class, 'scrape'])
        ->name('jobs.scrape');
    Route::get('/jobs/get-hs2-codes', [JobDispatchController::class, 'getHs2Codes'])
        ->name('jobs.get-hs2-codes');

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

    // Scrape job progress
    Route::get('/scrape-progress/{jobId}', [JobDispatchController::class, 'getScrapeProgress'])
        ->name('scrape-progress');
});