<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;

use App\Models\{User, Settings, Agent, Deposit, Tp_Transaction};
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Mail\DepositStatus;
use App\Traits\Coinpayment;
use Illuminate\Support\Facades\Mail;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, Coinpayment;

    // API version of paypalverify
    public function paypalverify($amount)
    {
        $user = Auth::user();

        // Save deposit
        $dp = new Deposit();
        $dp->amount = $amount;
        $dp->payment_mode = "PayPal";
        $dp->status = 'Processed';
        $dp->proof = "Paypal";
        $dp->plan = "0";
        $dp->user = $user->id;
        $dp->save();

        // Add funds
        $user->increment('account_bal', $amount);

        // Handle Referrals
        $settings = Settings::where('id', '1')->first();
        if (!empty($user->ref_by)) {
            $earnings = ($settings->referral_commission * $amount) / 100;
            
            Agent::where('agent', $user->ref_by)->increment('total_activated', 1);
            Agent::where('agent', $user->ref_by)->increment('earnings', $earnings);

            $agent = User::where('id', $user->ref_by)->first();
            if ($agent) {
                $agent->increment('account_bal', $earnings);
            }

            $this->getAncestors(User::all(), $amount, $user->id);
        }

        // Email notification
        Mail::to($user->email)->send(new DepositStatus($dp, $user, 'Successful Deposit'));

        // API Response instead of redirect
        return response()->json([
            'status' => 'success',
            'message' => 'Deposit Successful!',
            'deposit_id' => $dp->id
        ], 200);
    }

    // API version of ref
    public function ref(Request $request, $id)
    {
        if (isset($id)) {
            $request->session()->flush();
            if (User::where('username', $id)->exists()) {
                $request->session()->put('ref_by', $id);
                return response()->json(['status' => 'success', 'message' => 'Referral session set']);
            }
            return response()->json(['status' => 'error', 'message' => 'Invalid Referral Code'], 404);
        }
        return response()->json(['status' => 'error', 'message' => 'No ID provided'], 400);
    }

    // cpay remains essentially the same as it returns the result of the trait method
    public function cpay($amount, $coin, $ui, $msg)
    {
        return $this->paywithcp($amount, $coin, $ui, $msg);
    }

    public function getRefs($array, $parent, $level = 0)
    {
        $referedMembers = '';
        $array = User::all();
        foreach ($array as $entry) {
            if ($entry->ref_by == $parent) {
                // return "$entry->id <br>";
                $referedMembers .= '- ' . $entry->name . '<br/>';
                $referedMembers .= $this->getRefs($array, $entry->id, $level + 1);

                if ($level == 1) {
                    $referedMembers .= "1 <br>";
                } elseif ($level == 2) {
                    $referedMembers .= "2 <br>";
                } elseif ($level == 3) {
                    $referedMembers .= "3 <br>";
                } elseif ($level == 4) {
                    $referedMembers .= "4 <br>";
                } elseif ($level == 5) {
                    $referedMembers .= "5 <br>";
                } elseif ($level == 0) {
                    $referedMembers .= "0 <br>";
                }
            }
        }
        return $referedMembers;
    }

    //Get uplines
    function getAncestors($array, $deposit_amount, $parent = 0, $level = 0)
    {
        $referedMembers = '';
        $parent = User::where('id', $parent)->first();
        foreach ($array as $entry) {

            if ($entry->id == $parent->ref_by) {
                //get settings 
                $settings = Settings::where('id', '=', '1')->first();

                if ($level == 1) {
                    $earnings = $settings->referral_commission1 * $deposit_amount / 100;
                    //add earnings to ancestor balance
                    User::where('id', $entry->id)
                        ->update([
                            'account_bal' => $entry->account_bal + $earnings,
                            'ref_bonus' => $entry->ref_bonus + $earnings,
                        ]);

                    //create history
                    Tp_Transaction::create([
                        'user' => $entry->id,
                        'plan' => "Credit",
                        'amount' => $earnings,
                        'type' => "Ref_bonus",
                    ]);
                } elseif ($level == 2) {
                    $earnings = $settings->referral_commission2 * $deposit_amount / 100;
                    //add earnings to ancestor balance
                    User::where('id', $entry->id)
                        ->update([
                            'account_bal' => $entry->account_bal + $earnings,
                            'ref_bonus' => $entry->ref_bonus + $earnings,
                        ]);

                    //create history
                    Tp_Transaction::create([
                        'user' => $entry->id,
                        'plan' => "Credit",
                        'amount' => $earnings,
                        'type' => "Ref_bonus",
                    ]);
                } elseif ($level == 3) {
                    $earnings = $settings->referral_commission3 * $deposit_amount / 100;
                    //add earnings to ancestor balance
                    User::where('id', $entry->id)
                        ->update([
                            'account_bal' => $entry->account_bal + $earnings,
                            'ref_bonus' => $entry->ref_bonus + $earnings,
                        ]);

                    //create history
                    Tp_Transaction::create([
                        'user' => $entry->id,
                        'plan' => "Credit",
                        'amount' => $earnings,
                        'type' => "Ref_bonus",
                    ]);
                } elseif ($level == 4) {
                    $earnings = $settings->referral_commission4 * $deposit_amount / 100;
                    //add earnings to ancestor balance
                    User::where('id', $entry->id)
                        ->update([
                            'account_bal' => $entry->account_bal + $earnings,
                            'ref_bonus' => $entry->ref_bonus + $earnings,
                        ]);

                    //create history
                    Tp_Transaction::create([
                        'user' => $entry->id,
                        'plan' => "Credit",
                        'amount' => $earnings,
                        'type' => "Ref_bonus",
                    ]);
                } elseif ($level == 5) {
                    $earnings = $settings->referral_commission5 * $deposit_amount / 100;
                    //add earnings to ancestor balance
                    User::where('id', $entry->id)
                        ->update([
                            'account_bal' => $entry->account_bal + $earnings,
                            'ref_bonus' => $entry->ref_bonus + $earnings,
                        ]);

                    //create history
                    Tp_Transaction::create([
                        'user' => $entry->id,
                        'plan' => "Credit",
                        'amount' => $earnings,
                        'type' => "Ref_bonus",
                    ]);
                }

                if ($level == 6) {
                    break;
                }

                //$referedMembers .= '- ' . $entry->name . '- Level: '. $level. '- Commission: '.$earnings.'<br/>';
                $referedMembers .= $this->getAncestors($array, $deposit_amount, $entry->id, $level + 1);
            }
        }
        return $referedMembers;
    }
}