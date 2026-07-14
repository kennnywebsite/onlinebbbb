<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Settings;
use App\Models\Wdmethod;
use App\Models\Withdrawal;
use App\Mail\NewNotification;
use App\Helpers\NotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ManageWithdrawalController extends Controller
{
    /**
     * Get details for the withdrawal processing screen (Replaces processwithdraw view)
     */
    public function getWithdrawalDetails($id)
    {
        $withdrawal = Withdrawal::with('user')->find($id);
        if (!$withdrawal) {
            return response()->json(['message' => 'Withdrawal not found'], 404);
        }

        $method = Wdmethod::where('name', $withdrawal->payment_mode)->first();

        return response()->json([
            'status' => 200,
            'data' => [
                'withdrawal' => $withdrawal,
                'method' => $method,
                'user' => $withdrawal->user,
            ]
        ]);
    }

    /**
     * Process withdrawals
     */
    public function pwithdrawal(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:withdrawals,id',
            'action' => 'required|in:Paid,On-hold,Rejected'
        ]);

        $withdrawal = Withdrawal::findOrFail($request->id);
        $user = User::findOrFail($withdrawal->user);
        $settings = Settings::first();
        $createdAt = $request->date ?? $withdrawal->created_at;

        // 1. Logic for Paid
        if ($request->action === "Paid") {
            $withdrawal->update(['status' => 'Processed', 'created_at' => $createdAt]);

            NotificationHelper::create($user, 'Your withdrawal of ' . $withdrawal->amount . ' has been approved.', 'Withdrawal Approved', 'success', 'check-circle', '/withdrawals');

            $message = "Your transfer request of {$settings->currency}{$withdrawal->amount} to {$withdrawal->accountname} has been approved.";
            Mail::to($user->email)->send(new NewNotification($message, 'Successful Transfer', $user->name));
        }

        // 2. Logic for On-hold
        elseif ($request->action === "On-hold") {
            $withdrawal->update(['status' => 'On-hold', 'created_at' => $createdAt]);

            NotificationHelper::create($user, 'Your withdrawal of ' . $withdrawal->amount . ' is on hold.', 'Withdrawal On Hold', 'warning', 'alert-triangle', '/withdrawals');

            $message = "Your transfer request of {$settings->currency}{$withdrawal->amount} is currently On-hold. Please contact support.";
            Mail::to($user->email)->send(new NewNotification($message, 'On-hold Transaction', $user->name));
        }

        // 3. Logic for Rejected
        else {
            $user->increment('account_bal', $withdrawal->to_deduct);
            $withdrawal->update(['status' => 'Rejected', 'created_at' => $createdAt]);

            NotificationHelper::create($user, 'Your withdrawal was rejected and funds returned.', 'Withdrawal Rejected', 'danger', 'x-circle', '/withdrawals');

            if ($request->boolean('emailsend')) {
                Mail::to($user->email)->send(new NewNotification($request->reason, $request->subject, $user->name));
            }
        }

        return response()->json(['status' => 200, 'message' => 'Action Successful!']);
    }
}