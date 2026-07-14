<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Settings;
use App\Models\User_plans;
use App\Models\Tp_Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Mail\NewNotification;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class UserInvPlanController extends Controller
{
    // Apply for loan (API Version)
    public function loan(Request $request)
    {
        $request->validate([
            'income'   => 'required',
            'purpose'  => 'required',
            'duration' => 'required',
            'facility' => 'required',
        ]);

        $user = Auth::user();
        
        // Note: Ensure $plan_price and $end_at are defined based on your logic
        $plan_price = $request->amount; 
        $end_at = Carbon::now()->addMonths($request->duration);

        $userplanid = DB::table('user_plans')->insertGetId([
            'user'         => $user->id,
            'amount'       => $plan_price,
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

        User::where('id', $user->id)->update([
            'user_plan'  => $userplanid,
            'entered_at' => Carbon::now(),
        ]);

        $settings = Settings::where('id', '=', '1')->first();
        $message = "This is to inform you that {$user->name} just applied for a loan plan for {$request->purpose}";
        $subject = "Loan Application by {$user->name}";
        
        if ($settings) {
            Mail::to($settings->contact_email)->send(new NewNotification($message, $subject, 'Admin'));
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'You have successfully applied for a loan. Your loan is currently pending.'
        ], 201);
    }

    // Cancel Plan (API Version)
    public function cancelPlan($plan_id)
    {
        $plan = User_plans::find($plan_id);

        if (!$plan) {
            return response()->json(['message' => 'Plan not found'], 404);
        }

        if ($plan->user !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $plan->active = 'cancelled';
        $plan->save();

        // Credit the user
        $user = Auth::user();
        $user->account_bal += $plan->amount;
        $user->save();

        // Transaction history
        $th = new Tp_Transaction();
        $th->plan   = $plan->dplan->name ?? 'N/A';
        $th->user   = $plan->user;
        $th->amount = $plan->amount;
        $th->type   = "Investment capital for cancelled plan";
        $th->save();

        // Email
        $planName = $plan->dplan->name ?? 'Investment';
        $message  = "You have successfully cancelled your $planName plan.";
        Mail::to($user->email)->send(new NewNotification($message, 'Investment Plan Cancelled', $user->name));

        return response()->json([
            'status'  => 'success',
            'message' => 'Plan cancelled successfully'
        ]);
    }
}