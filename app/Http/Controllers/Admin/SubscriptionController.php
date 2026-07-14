<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mt4Details;
use App\Models\Settings;
use App\Traits\PingServer;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    use PingServer;

    /**
     * Get trading settings for dashboard
     */
    public function myTradingSettings()
    {
        $account = $this->fetctApi('/account-profile');
        $masters = $this->fetctApi('/master-account');
        $accounts = $this->fetctApi('/trading-accounts');
        $settings = $this->fetctApi('/settings');

        return response()->json([
            'status' => 200,
            'data' => [
                'my_account' => $account['data'] ?? null,
                'master_accounts' => $masters['data'] ?? [],
                'trading_accounts' => $accounts['data'] ?? [],
                'amount_per_slot' => $settings['data']['amount_per_slot'] ?? 0
            ]
        ]);
    }

    /**
     * Create copy master account
     */
    public function createCopyMasterAccount(Request $request)
    {
        $response = $this->fetctApi('/create-copytrade-account', [
            'login' => $request->login,
            'password' => $request->password,
            'serverName' => $request->serverName,
            'name' => $request->name,
            'leverage' => $request->leverage,
            'account_type' => $request->acntype,
            'baseCurrency' => $request->currency ?? 'USD',
        ], 'POST');

        return $this->apiResponse($response);
    }

    /**
     * Update strategy
     */
    public function updateStrategy(Request $request)
    {
        $modeCompliment = $request->input('fixedRisk') ?? 
                          $request->input('fixedVolume') ?? 
                          $request->input('expression') ?? '';

        $response = $this->fetctApi('/update-strategy', [
            'mode' => $request->trademode,
            'strategy_name' => $request->name,
            'description' => $request->desc,
            'modecompliment' => $modeCompliment,
        ], 'POST');

        return $this->apiResponse($response);
    }

    /**
     * Delete master account
     */
    public function deleteMasterAccount($id)
    {
        $response = $this->fetctApi('/delete-master-account/' . $id);
        return $this->apiResponse($response);
    }

    /**
     * Renew master account
     */
    public function renewAccount(Request $request)
    {
        $response = $this->fetctApi('/renew-master-account', [
            'account' => $request->account_id,
        ], 'POST');

        return $this->apiResponse($response);
    }

    /**
     * Delete subscription locally
     */
    public function delsub($id)
    {
        $deleted = Mt4Details::where('id', $id)->delete();
        
        if (!$deleted) {
            return response()->json(['status' => 'error', 'message' => 'Subscription not found'], 404);
        }

        return response()->json(['status' => 'success', 'message' => 'Subscription successfully deleted']);
    }

    /**
     * Standardized response helper
     */
    private function apiResponse($response)
    {
        if ($response->failed()) {
            return response()->json(['status' => 'error', 'message' => $response['message'] ?? 'Action failed'], 400);
        }
        return response()->json(['status' => 'success', 'message' => $response['message'] ?? 'Action successful']);
    }
}