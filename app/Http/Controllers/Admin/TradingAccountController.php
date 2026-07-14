<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mt4Details;
use App\Models\Settings;
use App\Models\User;
use App\Traits\PingServer;
use App\Mail\NewNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class TradingAccountController extends Controller
{
    use PingServer;

    /**
     * Get Provisioned Trading Accounts
     */
    public function tradingAccounts()
    {
        $response = $this->fetctApi('/trading-accounts');
        $apisettings = $this->fetctApi('/settings');
        $accounts = $this->fetctApi('/master-account');

        return response()->json([
            'status' => 200,
            'data' => [
                'accounts' => $response['data'] ?? [],
                'amountPerSlot' => $apisettings['data']['amount_per_slot'] ?? 0,
                'masters' => $accounts['data'] ?? [],
            ]
        ]);
    }

    /**
     * Renew Account
     */
    public function renewAccount(Request $request)
    {
        $response = $this->fetctApi('/renew-account', [
            'account' => $request->account_id,
        ], 'POST');

        return $this->handleExternalResponse($response);
    }

    /**
     * Create Subscriber Account
     */
    public function createSubscriberAccount(Request $request)
    {
        $response = $this->fetctApi('/create-sub-account', [
            'login' => $request->login,
            'password' => $request->password,
            'serverName' => $request->serverName,
            'name' => $request->name,
            'leverage' => $request->leverage,
            'account_type' => $request->acntype,
            'baseCurrency' => $request->currency ?? 'USD',
        ], 'POST');

        if ($response->successful() && $request->has('mt4id')) {
            $this->confirmsub($request->mt4id);
        }

        return $this->handleExternalResponse($response);
    }

    /**
     * Delete Subscriber Account
     */
    public function deleteSubAccount($id)
    {
        $response = $this->fetctApi('/delete-sub-account/' . $id);
        return $this->handleExternalResponse($response);
    }

    /**
     * Copy Trade Action
     */
    public function copyTrade(Request $request)
    {
        $response = $this->fetctApi('/copytrade', [
            'account' => $request->subscriberid,
            'master_account_id' => $request->master,
        ], 'POST');

        return $this->handleExternalResponse($response);
    }

    /**
     * Deployment Action
     */
    public function deployment($id, $deployment)
    {
        $response = $this->fetctApi('/deployment', [
            'account' => $id,
            'deploy_type' => $deployment,
        ], 'POST');

        return $this->handleExternalResponse($response);
    }

    /**
     * Helper to return standard API response based on PingServer response
     */
    private function handleExternalResponse($response)
    {
        if ($response->failed()) {
            return response()->json(['status' => 'error', 'message' => $response['message'] ?? 'Action failed'], 400);
        }
        return response()->json(['status' => 'success', 'message' => $response['message'] ?? 'Action successful']);
    }

    /**
     * Confirm Subscription (Internal DB update)
     */
    public function confirmsub($id)
    {
        $sub = Mt4Details::find($id);
        $user = User::find($sub->client_id);

        $durations = ['Monthly' => 1, 'Quaterly' => 4, 'Yearly' => 12];
        $months = $durations[$sub->duration] ?? 1;
        
        $end_at = now()->addMonths($months);
        $sub->update([
            'start_date' => now(),
            'end_date' => $end_at,
            'reminded_at' => $end_at->subDays(10),
            'status' => 'Active'
        ]);

        $settings = Settings::first();
        $message = "{$user->name}, your trading account management request has been processed. Thank you for trusting {$settings->site_name}";
        Mail::to($user->email)->send(new NewNotification($message, 'Subscription Account Started!', $user->name));
    }
}