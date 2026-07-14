<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Settings;
use App\Models\Deposit;
use App\Models\Tp_Transaction;
use App\Mail\DepositStatus;
use App\Helpers\NotificationHelper;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ManageDepositController extends Controller
{
    /**
     * Delete deposit history
     */
    public function deldeposit($id)
    {
        $deposit = Deposit::findOrFail($id);
        if ($deposit->proof) Storage::disk('public')->delete($deposit->proof);
        $deposit->delete();
        
        return response()->json(['status' => 'success', 'message' => 'Deposit deleted.']);
    }

    /**
     * Process deposit approval
     */
    public function pdeposit($id)
    {
        $deposit = Deposit::findOrFail($id);
        $user = User::findOrFail($deposit->user);
        $settings = Settings::first();

        // 1. Add funds
        $user->increment('account_bal', $deposit->amount);
        $user->update(['cstatus' => 'Customer']);

        // 2. Notify User
        NotificationHelper::create(
            $user,
            'Your deposit of ' . $settings->currency . $deposit->amount . ' has been approved.',
            'Deposit Approved',
            'success', 'check-circle', '/deposits'
        );

        // 3. Referral Commission Logic
        if (!empty($user->ref_by)) {
            $this->processReferralChain($user, $deposit->amount, $settings);
        }

        // 4. Confirm Email
        Mail::to($user->email)->send(new DepositStatus($deposit, $user, 'Your Deposit has been Confirmed', false));

        // 5. Update Status
        $deposit->update(['status' => 'Processed']);

        return response()->json(['status' => 'success', 'message' => 'Deposit processed successfully.']);
    }

    /**
     * Refactored Recursive Ancestor Logic (Clean & Efficient)
     */
    private function processReferralChain($user, $amount, $settings, $level = 1)
    {
        if ($level > 5) return;

        $referrer = User::find($user->ref_by);
        if (!$referrer) return;

        $commKey = ($level == 1) ? 'referral_commission' : 'referral_commission' . $level;
        $rate = $settings->$commKey ?? 0;
        $earnings = ($rate * $amount) / 100;

        if ($earnings > 0) {
            $referrer->increment('account_bal', $earnings);
            $referrer->increment('ref_bonus', $earnings);

            Tp_Transaction::create([
                'user' => $referrer->id,
                'plan' => "Credit",
                'amount' => $earnings,
                'type' => "Ref_bonus",
            ]);
        }

        // Recursive call for next level up
        $this->processReferralChain($referrer, $amount, $settings, $level + 1);
    }
}