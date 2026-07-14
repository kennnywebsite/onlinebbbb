<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Deposit, Tp_Transaction, User, Withdrawal, Settings};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewNotification;
use Carbon\Carbon;
use Twilio\Rest\Client;

class TopupController extends Controller
{
    /**
     * Handle manual credit/debit top-up
     */
    public function topup(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric',
            't_type' => 'required|in:Credit,Debit',
            'type' => 'required|string',
        ]);

        $user = User::findOrFail($request->user_id);
        $settings = Settings::first();
        
        // Process Logic
        if ($request->t_type === 'Credit') {
            $this->processCredit($request, $user, $settings);
        } else {
            $this->processDebit($request, $user, $settings);
        }

        return response()->json([
            'status' => 200, 
            'message' => "{$request->t_type} operation successful."
        ]);
    }

    private function processCredit($request, $user, $settings)
    {
        // Update user balances based on type
        switch ($request->type) {
            case 'Bonus': $user->increment('bonus', $request->amount); break;
            case 'Profit': $user->increment('roi', $request->amount); break;
            case 'Ref_Bonus': $user->increment('ref_bonus', $request->amount); break;
        }

        $user->increment('account_bal', $request->amount);

        // Record Transaction
        Withdrawal::create([
            'user' => $user->id,
            'amount' => $request->amount,
            'status' => 'Processed',
            'type' => 'Credit',
            'payment_mode' => $request->scope ?? 'Manual',
            'txn_id' => 'TXN-' . strtoupper(uniqid()),
        ]);

        Tp_Transaction::create([
            'user' => $user->id,
            'plan' => 'Credit',
            'amount' => $request->amount,
            'type' => $request->type
        ]);
    }

    private function processDebit($request, $user, $settings)
    {
        $user->decrement('account_bal', $request->amount);
        
        Withdrawal::create([
            'user' => $user->id,
            'amount' => $request->amount,
            'status' => 'Processed',
            'type' => 'Debit',
            'txn_id' => 'TXN-' . strtoupper(uniqid()),
        ]);
    }

    /**
     * Generate bulk transactions for testing/management
     */
    public function generateTransactions(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'number_of_transactions' => 'required|integer|min:1',
            'type' => 'required|in:credit,debit',
        ]);

        $user = User::find($request->user_id);
        $count = $request->number_of_transactions;

        for ($i = 0; $i < $count; $i++) {
            $amount = rand(10, 1000);
            
            Withdrawal::create([
                'user' => $user->id,
                'amount' => $amount,
                'type' => ucfirst($request->type),
                'status' => 'Processed',
                'txn_id' => 'AUTO-' . strtoupper(uniqid()),
            ]);

            if ($request->type === 'credit') $user->increment('account_bal', $amount);
            else $user->decrement('account_bal', $amount);
        }

        return response()->json([
            'status' => 200, 
            'message' => "Successfully generated {$count} transactions."
        ]);
    }
}