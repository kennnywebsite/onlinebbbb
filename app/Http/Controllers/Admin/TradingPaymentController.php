<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Settings; // Assuming you store global payment settings here
use Illuminate\Http\Request;

class TradingPaymentController extends Controller
{
    /**
     * Return payment configuration for the Admin dashboard.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function payment()
    {
        // Fetch relevant admin settings to populate the payment management UI
        $settings = Settings::first(['site_name', 's_currency', 'deposit_bonus']);

        return response()->json([
            'status' => 200,
            'title' => 'Fund Account Management',
            'data' => [
                'config' => $settings,
                'payment_gateways' => [
                    'stripe' => ['enabled' => true], // Example status
                    'flutterwave' => ['enabled' => true]
                ]
            ]
        ]);
    }
}