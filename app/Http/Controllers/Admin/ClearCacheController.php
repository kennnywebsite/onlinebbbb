<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class ClearCacheController extends Controller
{
    /**
     * Clear system caches via API
     */
    public function clearCache()
    {
        try {
            Artisan::call('view:clear');
            Artisan::call('cache:clear');
            Artisan::call('config:clear'); // Often good practice to clear this too

            return response()->json([
                'status' => 200, 
                'message' => 'System cache cleared successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Failed to clear cache: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle license validation via API
     * Note: Hardcoding keys inside a controller is a security risk. 
     * Use config() or .env files instead.
     */
    public function saveLicense(Request $request)
    {
        $request->validate(['license' => 'required|string']);

        $website = url('/'); // Laravel helper for the base URL

        try {
            $response = Http::post('http://127.0.0.1:8080/api/v1/save-license', [
                'license' => $request->license,
                'website' => $website
            ]);

            if ($response->successful()) {
                return response()->json(['status' => 200, 'message' => 'License verified successfully.']);
            }

            return response()->json(['status' => 'error', 'message' => 'License verification failed.'], 400);
            
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Connection error.'], 500);
        }
    }
}