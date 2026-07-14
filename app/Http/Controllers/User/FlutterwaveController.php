<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Settings;
use App\Models\Tp_Transaction;
use App\Models\User;
use App\Mail\DepositStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use KingFlamez\Rave\Facades\Rave as Flutterwave;

class FlutterwaveController extends Controller
{
    /**
     * API: Initialize payment and return the checkout URL
     */
    public function initialize(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric',
            'email'  => 'required|email',
        ]);

        $reference = Flutterwave::generateReference();
        $settings = Settings::first();
        
        $data = [
            'payment_options' => 'card,banktransfer',
            'amount'          => intval($request->amount),
            'email'           => $request->email,
            'tx_ref'          => $reference,
            'currency'        => $settings->s_currency,
            'redirect_url'    => config('app.url') . '/api/payment/callback', // API callback route
            'customer'        => [
                'email'        => $request->email,
                'phone_number' => $request->phone,
                'name'         => $request->name
            ],
            'customizations' => [
                'title'       => 'Deposit',
                'description' => "Funding account balance"
            ]
        ];

        $payment = Flutterwave::initializePayment($data);

        if ($payment['status'] !== 'success') {
            return response()->json(['status' => 'error', 'message' => 'Payment initiation failed'], 400);
        }

        return response()->json([
            'status' => 'success',
            'authorization_url' => $payment['data']['link']
        ]);
    }

    /**
     * API: Callback endpoint
     */
    public function callback(Request $request)
    {
        $status = $request->status;

        if ($status !== 'successful') {
            return response()->json(['status' => 'failed', 'message' => 'Payment not successful'], 400);
        }

        $transactionID = Flutterwave::getTransactionIDFromCallback();
        $data = Flutterwave::verifyTransaction($transactionID);
        
        if (!$data || $data['status'] !== 'success') {
            return response()->json(['status' => 'error', 'message' => 'Transaction verification failed'], 400);
        }

        $amount = $data['data']['amount'];
        $user = Auth::user();
        $settings = Settings::first();
        $earnings = ($settings->referral_commission * $amount) / 100;

        // Process Deposit
        Deposit::create([
            'user'         => $user->id,
            'amount'       => $amount,
            'txn_id'       => $data['data']['tx_ref'],
            'payment_mode' => 'Flutterwave',
            'status'       => 'Processed',
            'proof'        => 'Credit Card',
            'plan'         => '0',
        ]);

        // Bonus Logic
        $bonus = ($settings->deposit_bonus > 0) ? ($amount * $settings->deposit_bonus / 100) : 0;
        if ($bonus > 0) {
            Tp_Transaction::create([
                'user' => $user->id,
                'plan' => "Deposit Bonus",
                'amount' => $bonus,
                'type' => "Bonus",
            ]);
        }

        // Update Balances
        $user->increment('account_bal', ($amount + $bonus));
        $user->update(['bonus' => $user->bonus + $bonus, 'cstatus' => 'Customer']);

        // Referral Logic
        if ($user->ref_by) {
            $agent = User::find($user->ref_by);
            if ($agent) {
                $agent->increment('account_bal', $earnings);
                $agent->increment('ref_bonus', $earnings);
                $this->processAncestors($amount, $user->ref_by);
            }
        }

        Mail::to($user->email)->send(new DepositStatus(null, $user, "Successful deposit!"));

        return response()->json(['status' => 'success', 'message' => 'Payment Successful']);
    }

    /**
     * Refactored Recursive Ancestor Logic (Efficiency Update)
     */
    private function processAncestors($amount, $parentId, $level = 1)
    {
        if ($level > 5) return;

        $parent = User::find($parentId);
        if (!$parent || !$parent->ref_by) return;

        $ancestor = User::find($parent->ref_by);
        $settings = Settings::first();
        
        $commRate = $settings->{"referral_commission" . $level} ?? 0;
        $earnings = ($commRate * $amount) / 100;

        if ($earnings > 0) {
            $ancestor->increment('account_bal', $earnings);
            $ancestor->increment('ref_bonus', $earnings);
            
            Tp_Transaction::create([
                'user' => $ancestor->id,
                'plan' => "Credit",
                'amount' => $earnings,
                'type' => "Ref_bonus",
            ]);
        }

        $this->processAncestors($amount, $ancestor->id, $level + 1);
    }
}