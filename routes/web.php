<?php

use App\Http\Controllers\Admin\ClearCacheController;
use App\Models\Settings;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// External route files
// Note: Ensure your admin.php, user.php, etc., use 'auth:admin' for admin routes
require __DIR__ . '/home.php';
require __DIR__ . '/admin.php';
require __DIR__ . '/user.php';
require __DIR__ . '/botman.php';

// Public Views
Route::view('/offline', 'vendor.laravelpwa.offline');

Route::any('/activate', function () {
    return view('activate.index', ['settings' => Settings::find(1)]);
});

Route::any('/revoke', function () {
    return view('revoke.index');
});

// Licensing
Route::get('register-license', [ClearCacheController::class, 'saveLicense']);

// Auth
Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware(['guest:' . config('fortify.guard')])
    ->name('password.update');

/**
 * DANGER ZONE: Protected Admin Utilities
 * We now use 'auth:admin' to ensure the middleware checks the 'admin' session
 */
Route::middleware(['auth:admin'])->group(function () {
    Route::get('/clear-cache', function() {
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        return "Cache cleared successfully!";
    });

    Route::get('/run-migrations-now', function() {
        Artisan::call('migrate --force');
        return "Migrations have been run!";
    });
});