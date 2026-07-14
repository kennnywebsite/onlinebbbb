<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Settings;
use App\Models\Tp_Transaction;
use App\Models\User;
use App\Models\Wdmethod;
use App\Helpers\NotificationHelper;
use App\Mail\DepositStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Exception;

class DepositController extends Controller
{
    /**
     * Fetch all payment methods
     */
    public function getMethods()
    {
        return response()->json(['methods' => Wdmethod::all()]);
    }

    /**
     * Initialize Stripe for Mobile/Frontend (Returns Client Secret)
     */
    public function initializeStripe(Request $request)
    {
        $validator = Validator::make($request->all(), ['amount' => 'required|numeric|min:1']);
        if ($validator->fails()) return response()->json(['error' => $validator->errors()], 422);

        try {
            $settings = Settings::first();
            \Stripe\Stripe::setApiKey($settings->s_s_k);

            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => (int)($request->amount * 100),
                'currency' => strtolower($settings->s_currency),
                'payment_method_types' => ['card'],
            ]);

            return response()->json([
                'client_secret' => $paymentIntent->client_secret,
                'amount' => $request->amount
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Stripe Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Finalize the deposit once payment is confirmed on frontend
     */
    public function finalizeStripe(Request $request)
    {
        $request->validate(['amount' => 'required|numeric']);
        $user = Auth::user();
        $settings = Settings::first();

        $dp = Deposit::create([
            'user' => $user->id,
            'amount' => $request->amount,
            'payment_mode' => 'Stripe',
            'status' => 'Processed',
            'proof' => 'API Payment'
        ]);

        $this->processBalances($user, $request->amount, $settings);
        
        return response()->json(['status' => 200, 'message' => 'Deposit successful']);
    }

    /**
     * Manual deposit with file upload
     */
    public function savedeposit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric',
            'payment_method' => 'required|string',
            'proof' => 'required|image|max:2048'
        ]);

        $user = Auth::user();
        if ($user->account_status !== 'active') {
            return response()->json(['message' => 'Account is dormant.'], 403);
        }

        $path = $request->file('proof')->store('uploads', 'public');

        $dp = Deposit::create([
            'user' => $user->id,
            'amount' => $request->amount,
            'payment_mode' => $request->payment_method,
            'status' => 'Pending',
            'proof' => $path,
            'txn_id' => 'TXN-' . strtoupper(bin2hex(random_bytes(4)))
        ]);

        // Notify user/admin
        Mail::to($user->email)->send(new DepositStatus($dp, $user, 'Deposit Submitted', false));

        return response()->json(['status' => 200, 'message' => 'Deposit submitted for approval.']);
    }

    /**
     * Centralized logic for balances, bonuses, and referrals
     */
    private function processBalances($user, $amount, $settings)
    {
        // 1. Bonus logic
        $bonus = ($settings->deposit_bonus > 0) ? ($amount * $settings->deposit_bonus / 100) : 0;
        
        $user->increment('account_bal', ($amount + $bonus));
        $user->increment('bonus', $bonus);
        
        Tp_Transaction::create([
            'user' => $user->id,
            'plan' => "Deposit Bonus",
            'amount' => $bonus,
            'type'   => "Bonus"
        ]);

        // 2. Direct Referral Logic
        if (!empty($user->ref_by)) {
            $agent = User::find($user->ref_by);
            if ($agent) {
                $earnings = ($settings->referral_commission * $amount) / 100;
                
                $agent->increment('account_bal', $earnings);
                $agent->increment('ref_bonus', $earnings);

                Tp_Transaction::create([
                    'user'   => $agent->id,
                    'plan'   => "Credit",
                    'amount' => $earnings,
                    'type'   => "Ref_bonus",
                ]);

                // 3. Ancestor Chain Logic
                $this->processAncestors($amount, $user->ref_by);
            }
        }
    }

    /**
     * Recursive function for multi-level referral chain
     */
    private function processAncestors($amount, $parentId, $level = 1)
    {
        if ($level > 5) return;

        $parent = User::find($parentId);
        if (!$parent || !$parent->ref_by) return;

        $ancestor = User::find($parent->ref_by);
        $settings = Settings::first();
        
        $commKey = "referral_commission" . $level;
        $rate = $settings->$commKey ?? 0;
        $earnings = ($rate * $amount) / 100;

        if ($earnings > 0) {
            $ancestor->increment('account_bal', $earnings);
            $ancestor->increment('ref_bonus', $earnings);
            
            Tp_Transaction::create([
                'user'   => $ancestor->id,
                'plan'   => "Credit",
                'amount' => $earnings,
                'type'   => "Ref_bonus",
            ]);
        }

        $this->processAncestors($amount, $ancestor->id, $level + 1);
    }
}