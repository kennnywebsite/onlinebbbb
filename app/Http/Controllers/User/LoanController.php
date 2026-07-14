<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Settings;
use App\Models\User_plans;
use App\Helpers\NotificationHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Mail\NewNotification;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class LoanController extends Controller
{
    public function loan(Request $request)
    {
        // 1. Validation
        $request->validate([
            'amount'   => 'required|numeric',
            'income'   => 'required',
            'purpose'  => 'required',
            'duration' => 'required|integer',
            'facility' => 'required',
        ]);

        $user = Auth::user();
        $settings = Settings::find(1);

        // 2. Check Account Status
        if ($user->account_status !== 'active') {
            return response()->json([
                'status'  => 403,
                'message' => "Sorry, your account is dormant. Contact support on {$settings->contact_email} for details."
            ], 403);
        }

        // 3. Define expiration date
        $end_at = Carbon::now()->addMonths($request->duration);

        // 4. Save Loan
        $userplanid = DB::table('user_plans')->insertGetId([
            'user'         => $user->id,
            'amount'       => $request->amount,
            'income'       => $request->income,
            'purpose'      => $request->purpose,
            'duration'     => $request->duration,
            'facility'     => $request->facility,
            'active'       => 'Pending',
            'inv_duration' => $request->duration,
            'expire_date'  => $end_at,
            'activated_at' => Carbon::now(),
            'last_growth'  => Carbon::now(),
            'created_at'   => Carbon::now(),
            'updated_at'   => Carbon::now(),
        ]);

        $user->update([
            'user_plan'  => $userplanid,
            'entered_at' => Carbon::now(),
        ]);

        // 5. Create Internal Notification
        NotificationHelper::create(
            $user,
            "Your loan application for {$request->amount} has been submitted successfully and is pending approval.",
            'Loan Application Submitted',
            'info',
            'file-text',
            '/loan-details' // API frontend route instead of route()
        );

        // 6. Send Admin Email
        $message = "This is to inform you that {$user->name} just applied for a loan plan for {$request->purpose}";
        $subject = "Loan Application by {$user->name}";
        Mail::to($settings->contact_email)->send(new NewNotification($message, $subject, 'Admin'));

        return response()->json([
            'status'  => 200,
            'message' => "You have successfully applied for a loan. It is currently pending."
        ]);
    }

    public function viewLoans()
    {
        $loans = User_plans::where('user', Auth::id())
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status' => 200,
            'data'   => $loans
        ]);
    }
}