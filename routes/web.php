<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JobDispatchController;
use App\Http\Controllers\TradeDashboardController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/jobs/scrape', [JobDispatchController::class, 'scrape'])->name('jobs.scrape');



// Trade Data Dashboard Routes
Route::prefix('dashboard')->name('dashboard.')->group(function () {
    
    // Main dashboard
    Route::get('/', [TradeDashboardController::class, 'index'])
        ->name('trade-data');
    
    // Jobs page
    Route::get('/jobs', [TradeDashboardController::class, 'showJobsPage'])
        ->name('jobs');

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