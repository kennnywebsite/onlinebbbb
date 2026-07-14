<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SettingsCont;

class ManageAssetController extends Controller
{
    /**
     * Set the status of a specific crypto asset (e.g., btc: true/false)
     */
    public function setAssetStatus($asset, $status)
    {
        // Sanitize: Ensure only valid columns are updated if needed
        $allowed = ['btc', 'eth', 'usdt', 'ltc', 'bnb', 'xrp', 'ada', 'xlm', 'aave', 'bch', 'link'];
        
        if (!in_array($asset, $allowed)) {
            return response()->json(['status' => 400, 'message' => 'Invalid asset provided.'], 400);
        }

        SettingsCont::where('id', 1)->update([
            $asset => $status,
        ]);

        return response()->json([
            'status' => 200, 
            'message' => "Asset {$asset} status set to {$status}"
        ]);
    }

    /**
     * Toggle the global exchange feature
     */
    public function useExchange($value)
    {
        SettingsCont::where('id', 1)->update([
            'use_crypto_feature' => $value,
        ]);

        return response()->json([
            'status' => 200, 
            'message' => "Exchange feature status updated to: {$value}"
        ]);
    }

    /**
     * Update exchange fees and currency rates
     */
    public function exchangeFee(Request $request)
    {
        $request->validate([
            'fee'  => 'required|numeric|min:0',
            'rate' => 'nullable|numeric|min:0'
        ]);

        SettingsCont::where('id', 1)->update([
            'fee'           => $request->fee,
            'currency_rate' => $request->rate ?? null
        ]);

        return response()->json([
            'status' => 200, 
            'message' => "Exchange fee and Rate Updated successfully"
        ]);
    }
}