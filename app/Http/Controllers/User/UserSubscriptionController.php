<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{User, Mt4Details, Settings, Tp_Transaction};
use App\Mail\NewNotification;
use Illuminate\Support\Facades\{Auth, Mail, Validator};

class UserSubscriptionController extends Controller
{
    // Save MT4 details
    public function savemt4details(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userid' => 'required',
            'pswrd' => 'required',
            'amount' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        
        if ($user->account_bal < $request->amount) {
            return response()->json(['status' => 'error', 'message' => 'Insufficient balance.'], 400);
        }

        $user->decrement('account_bal', $request->amount);

        $mt4 = new Mt4Details;
        $mt4->client_id = $user->id;
        $mt4->mt4_id = $request->userid;
        $mt4->mt4_password = $request->pswrd;
        $mt4->account_type = $request->acntype;
        $mt4->account_name = $request->name;
        $mt4->currency = $request->currency;
        $mt4->leverage = $request->leverage;
        $mt4->server = $request->server;
        $mt4->duration = $request->duration;
        $mt4->status = 'Pending';
        $mt4->save();

        Tp_Transaction::create([
            'user' => $user->id,
            'plan' => "Subscribed MT4 Trading",
            'amount' => $request->amount,
            'type' => "MT4 Trading",
        ]);

        $settings = Settings::find(1);
        Mail::to($settings->contact_email)->send(new NewNotification(
            "MT4 details submitted by $user->name", 'MT4 Details submitted', 'Admin'
        ));

        return response()->json(['status' => 'success', 'message' => 'Subscription request submitted successfully.']);
    }

    // Delete MT4 details
    public function delsubtrade($id)
    {
        $mt4 = Mt4Details::find($id);
        if (!$mt4) return response()->json(['message' => 'Record not found'], 404);
        
        $mt4->delete();
        return response()->json(['status' => 'success', 'message' => 'MT4 Details Deleted']);
    }

    // Renew Subscription
    public function renewSubscription($id)
    {
        $account = Mt4Details::find($id);
        $user = Auth::user();
        $settings = Settings::find(1);

        if (!$account) return response()->json(['message' => 'Account not found'], 404);

        $durations = [
            'Monthly' => ['amount' => $settings->monthlyfee, 'add' => 'addMonths(1)'],
            'Quaterly' => ['amount' => $settings->quarterlyfee, 'add' => 'addMonths(4)'],
            'Yearly' => ['amount' => $settings->yearlyfee, 'add' => 'addYears(1)'],
        ];

        $plan = $durations[$account->duration] ?? null;
        if (!$plan) return response()->json(['message' => 'Invalid duration'], 400);

        if ($plan['amount'] > $user->account_bal) {
            return response()->json(['status' => 'error', 'message' => 'Insufficient balance.'], 400);
        }

        // Apply logic
        $user->decrement('account_bal', $plan['amount']);
        
        // Use eval or dynamic manipulation for the dates
        $account->start_date = now();
        $account->end_date = ($account->duration == 'Monthly') ? $account->end_date->addMonths(1) : (($account->duration == 'Quaterly') ? $account->end_date->addMonths(4) : $account->end_date->addYears(1));
        $account->reminded_at = $account->end_date->subDays(10);
        $account->status = 'Active';
        $account->save();

        Mail::to($account->tuser->email)->send(new NewNotification("Subscription renewed.", 'Renewed', $user->firstname));
        
        return response()->json(['status' => 'success', 'message' => 'Subscription renewed successfully.']);
    }
}