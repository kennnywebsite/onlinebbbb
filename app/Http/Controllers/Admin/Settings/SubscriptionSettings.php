<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Settings;

class SubscriptionSettingsController extends Controller
{
    /**
     * Get subscription settings for the dashboard
     */
    public function index()
    {
        $settings = Settings::first();
        
        return response()->json([
            'status' => 200,
            'data' => [
                'monthly_fee'          => $settings->monthlyfee,
                'quarterly_fee'        => $settings->quarterlyfee,
                'yearly_fee'           => $settings->yearlyfee,
                'subscription_service' => $settings->subscription_service,
            ]
        ]);
    }
    
    /**
     * Update Subscription Fees
     */
    public function updateSubFee(Request $request)
    {
        $request->validate([
            'id'                   => 'required|exists:settings,id',
            'monthlyfee'           => 'required|numeric|min:0',
            'quaterlyfee'          => 'required|numeric|min:0',
            'yearlyfee'            => 'required|numeric|min:0',
            'subscription_service' => 'required|string',
        ]);
        
        Settings::where('id', $request->id)->update([
            'monthlyfee'           => $request->monthlyfee,
            'quarterlyfee'         => $request->quaterlyfee,
            'yearlyfee'            => $request->yearlyfee,
            'subscription_service' => $request->subscription_service,
        ]);

        return response()->json([
            'status'  => 200, 
            'message' => 'Subscription Settings Saved successfully'
        ]);
    }
}