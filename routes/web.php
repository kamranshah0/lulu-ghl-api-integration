<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Web Routes — Forever Wellthy Middleware
|--------------------------------------------------------------------------
*/

// ── Auth Routes ──────────────────────────────────────────────────────────
Route::get('/admin/login', [AuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('admin.login.post');
Route::post('/admin/logout', [AuthController::class, 'logout'])->middleware('auth')->name('admin.logout');

// ── Admin Dashboard (auth required) ─────────────────────────────────────
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Orders
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('/failed', [OrderController::class, 'failed'])->name('failed');
        Route::get('/export', [OrderController::class, 'export'])->name('export');
        Route::get('/{order}', [OrderController::class, 'show'])->name('show');
        Route::post('/{order}/retry', [OrderController::class, 'retry'])->name('retry');
    });

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
});

// Redirect root to admin
Route::redirect('/', '/admin');
