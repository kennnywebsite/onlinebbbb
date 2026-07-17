<?php

use App\Http\Controllers\Auth\ApiAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::post('/create-account', [ApiAuthController::class, 'register']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Added Health Check Route
Route::get('/up-db', function () {
    try {
        // This confirms the connection is alive
        DB::connection()->getPdo();
        
        return response('OK', 200);
    } catch (\Exception $e) {
        // Log the details internally
        Log::error('Database health check failed: ' . $e->getMessage());
        
        // Return 503 Service Unavailable to UptimeRobot
        return response('Service Unavailable', 503);
    }
});