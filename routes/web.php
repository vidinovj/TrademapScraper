<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TradeDashboardController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
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
});
