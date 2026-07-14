<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Mail\NewNotification;
use App\Models\Settings;
use App\Models\SettingsCont;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Tp_Transaction;
use App\Traits\PingServer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class TransferController extends Controller
{
    use PingServer;

    public function transfertouser(Request $request)
    {

        $receiver = User::where('email', $request->email)->orWhere('username', $request->email)->first();
        $sender = Auth::user();
        $settings = Settings::find(1);
        $settingss = SettingsCont::find(1);
        $charges = $request->amount * $settingss->transfer_charges / 100;
        $todeduct = $request->amount + $charges;

        if (!Hash::check($request->password, $sender->password)) {
            return response()->json([
                'status' => 419,
                'message' => 'Incorrect Password',
            ]);
        }

        if (($request->email == $sender->email) or ($request->email == $sender->username)) {
            return response()->json([
                'status' => 419,
                'message' => 'You cannot send funds to yourself',
            ]);
        }
        if (!$receiver) {
            return response()->json([
                'status' => 419,
                'message' => 'No user with this email address exist',
            ]);
        }

        if ($sender->account_bal < $todeduct) {
            return response()->json([
                'status' => 419,
                'message' => 'Insufficient Funds',
            ]);
        }

        $user = User::find(Auth::user()->id);
        $user->account_bal = $sender->account_bal - $todeduct;
        $user->save();

        User::where('email', $request->email)->orWhere('username', $request->email)->update([
            'account_bal' => $receiver->account_bal + $request->amount,
        ]);

        //create history
        Tp_Transaction::create([
            'user' => $sender->id,
            'plan' => "Transfered to $receiver->name",
            'amount' => $request->amount,
            'type' => "Fund Transfer",
        ]);

        //create history for receiver
        Tp_Transaction::create([
            'user' => $receiver->id,
            'plan' => "Received from $sender->name",
            'amount' => $request->amount,
            'type' => "Fund Transfer",
        ]);


        $message = "You just received $settings->currency$request->amount from $sender->name and your account balance is now $settings->currency$receiver->account_bal";

        Mail::to($receiver->email)->send(new NewNotification($message, 'Credit Alert', $receiver->name));

        return response()->json([
            'status' => 200,
            'message' => 'Transfer Completed, Refreshing page',
        ]);
    }

    /**
     * API Version of renewSignalSub
     */
    public function renewSignalSub()
    {
        $user = Auth::user();
        
        $response = $this->fetctApi('/subscription', [
            'id' => $user->id
        ]);
        
        $res = json_decode($response);
        if (!$res || !isset($res->data)) {
            return response()->json(['status' => 500, 'message' => 'Failed to fetch subscription data'], 500);
        }
        
        $sub = $res->data;

        $responseSt = $this->fetctApi('/signal-settings');
        $info = json_decode($responseSt);
        $settings = $info->data->settings;

        // Determine amount based on plan
        $amount = 0;
        if ($sub->subscription == 'Monthly') {
            $amount = $settings->signal_monthly_fee;
        } elseif ($sub->subscription == 'Quarterly') {
            $amount = $settings->signal_quartly_fee;
        } else {
            $amount = $settings->signal_yearly_fee;
        }

        // Check funds
        if ($user->account_bal < floatval($amount)) {
            return response()->json([
                'status' => 400, 
                'message' => 'Insufficient funds to perform this operation'
            ], 400);
        }

        // Perform renewal
        $renew = $this->fetctApi('/renew-subscription', [
            'id' => $user->id,
        ], 'POST');

        // Check if the external API call was successful
        // Assuming $renew is an Illuminate\Http\Client\Response
        if ($renew->successful()) {
            $user->account_bal -= floatval($amount);
            $user->save();
            
            return response()->json([
                'status' => 200,
                'message' => 'Your subscription has been renewed successfully.'
            ]);
        }

        return response()->json([
            'status' => 500,
            'message' => 'Something went wrong while communicating with the subscription server.'
        ], 500);
    }
}