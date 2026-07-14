<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Settings;
use App\Models\Deposit;
use App\Models\Tp_Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\DepositStatus;
use Unicodeveloper\Paystack\Facades\Paystack;

class PaystackController extends Controller
{
    /**
     * API: Generates the URL for the frontend to open in a WebView.
     */
    public function redirectToGateway(Request $request)
    {
        try {
            // Note: In an API, return the URL instead of redirecting directly
            $url = Paystack::getAuthorizationUrl()->url;
            return response()->json(['status' => 'success', 'authorization_url' => $url]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Payment gateway unavailable.'], 500);
        }
    }

    /**
     * API: Handles the callback from Paystack.
     */
    public function handleGatewayCallback()
    {
        try {
            $paymentDetails = Paystack::getPaymentData();
            
            if ($paymentDetails['status'] !== true) {
                return response()->json(['status' => 'error', 'message' => 'Payment verification failed'], 400);
            }

            $payamount = $paymentDetails['data']['amount'];
            $txn_ref = $paymentDetails['data']['reference'];
            $amount = $payamount / 100;

            $user = Auth::user();
            $settings = Settings::first();
            $earnings = $settings->referral_commission * $amount / 100;

            // 1. Save Deposit
            $dp = Deposit::create([
                'amount' => $amount,
                'txn_id' => $txn_ref,
                'payment_mode' => "Paystack",
                'status' => 'Processed',
                'proof' => "Credit Card",
                'plan' => "0",
                'user' => $user->id,
            ]);

            // 2. Handle Bonus
            $bonus = ($settings->deposit_bonus > 0) ? ($amount * $settings->deposit_bonus / 100) : 0;
            if ($bonus > 0) {
                Tp_Transaction::create([
                    'user' => $user->id,
                    'plan' => "Deposit Bonus for $settings->currency $amount",
                    'amount' => $bonus,
                    'type' => "Bonus",
                ]);
            }

            // 3. Update User Balance
            $user->increment('account_bal', ($amount + $bonus));
            $user->update(['bonus' => $user->bonus + $bonus, 'cstatus' => 'Customer']);

            // 4. Handle Referrals
            if (!empty($user->ref_by)) {
                $agent = User::find($user->ref_by);
                if ($agent) {
                    $agent->increment('account_bal', $earnings);
                    $agent->increment('ref_bonus', $earnings);

                    Tp_Transaction::create([
                        'user' => $agent->id,
                        'plan' => "Credit",
                        'amount' => $earnings,
                        'type' => "Ref_bonus",
                    ]);

                    $this->getAncestors(User::all(), $amount, $user->id);
                }
            }

            Mail::bcc($user->email)->send(new DepositStatus($dp, $user, "Successful deposit!"));

            return response()->json(['status' => 200, 'message' => 'Payment Successful']);
            
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error processing payment'], 500);
        }
    }

    // Refactored Ancestor Logic (Cleaned up redundant code)
    private function getAncestors($array, $deposit_amount, $parent_id, $level = 1)
    {
        if ($level > 5) return;

        $parent = User::find($parent_id);
        if (!$parent || !$parent->ref_by) return;

        $ancestor = User::find($parent->ref_by);
        if (!$ancestor) return;

        $settings = Settings::find(1);
        $commField = "referral_commission" . $level;
        $rate = $settings->$commField ?? 0;
        $earnings = ($rate * $deposit_amount) / 100;

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

        $this->getAncestors($array, $deposit_amount, $ancestor->id, $level + 1);
    }
}