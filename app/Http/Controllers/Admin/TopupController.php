<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Tp_Transaction;
use App\Models\User;

use App\Traits\PingServer;
use Illuminate\Http\Request;
use App\Models\Withdrawal;
use App\Models\Settings;
use App\Mail\NewNotification;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Twilio\Rest\Client;
use App\Traits\Apitrait;

class TopupController extends Controller
{
    use PingServer;
   use Apitrait;

    //top up route
    public function topup(Request $request)
    {


    $user = User::where('id', $request->user_id)->first();
        $userdpo = Deposit::where('user', $request['user_id'])->first();
        $settings = Settings::where('id', '1')->first();
        $user_bal=$user->account_bal;
        $user_bonus=$user->bonus;
        $user_roi=$user->roi;
        $user_Ref=$user->ref_bonus;
        $user_deposit = $userdpo->amount;

        $settings = Settings::where('id', '1')->first();
        $bank_name = strtoupper(substr($settings->site_name, 0, 3)); // Get first 3 letters
    $txn_id = $bank_name . '-' . substr(md5(uniqid(mt_rand(), true)), 0, 10);

        if($request['t_type']=="Credit") {
            if ($request['type']=="Bonus") {
                User::where('id', $request['user_id'])
                ->update([
                'bonus'=> $user_bonus + $request['amount'],
                'account_bal'=> $user_bal + $request->amount,
                ]);
            }elseif ($request['type']=="Profit") {
                User::where('id', $request->user_id)
                ->update([
                    'roi'=> $user_roi + $request->amount,
                    'account_bal'=> $user_bal + $request->amount,
                ]);
            }elseif($request['type']=="Ref_Bonus"){
                User::where('id', $request->user_id)
                ->update([
                    'ref_bonus'=> $user_Ref + $request->amount,
                    'account_bal'=> $user_bal + $request->amount,
                ]);

            }elseif ($request['type']=="balance") {
                $bal =$user_bal - $request->amount;
                $dp=new Withdrawal();
                $dp->amount= $request['amount'];
                $dp->payment_mode = $request['scope'];
                $dp->Description = $request['Description'];
                $dp->created_at  = $request['date'];
                $dp->type    = $request['t_type'];
                $dp->accountname = $request['name'];
                $dp->bankname = $settings->site_name;
                $dp->accountnumber = $user->usernumber;
                $dp->Accounttype = $user->accounttype;
                $dp->country  = $user->country;
                $dp->bankaddress = $settings->address;
                $dp->status= 'Processed';

                $dp->user= $request['user_id'];
                $dp->txn_id = $txn_id ;
                $dp->bal = $bal ;
                $dp->save();

                User::where('id', $request['user_id'])
                ->update([
                    'account_bal'=> $user_bal + $request->amount,
                ]);


                $bala =  $user_bal +$request->amount;

               if($request['notifymailuser']==1){
                 // send notification
         $settings=Settings::where('id', '=', '1')->first();
         $date  = Carbon::parse($dp->created_at)->toDayDateTimeString();
        $message = "Your account have been Credited.
        \r\nDetails of the transaction are shown below;
        \r\nAccount Number: $user->usernumber
        \r\nAccount Name: $user->name $user->middlename $user->lastname
         \r\nDescription: $request->Description
         \r\nTotal Amount:$request->amount$settings->s_currency
         \r\nDate: $date
         \r\nAvailable Balance:$bala$settings->s_currency";

        $subject ="Credit alert Notification[$request->amount$settings->s_currency]";
        Mail::to( $user->email)->send(new NewNotification($message, $subject, $user->name));


        if($settings->sms=='1'){
            $receiverNumber = $user->phone;
        $message = "Your account have been Credited.
        \nDetails of the transaction are shown below;
     \nAccount Number: $dp->accountnumber
     \nAccount Name: $user->name $user->middlename $user->lastname
     \nDescription: $request->Description
     \nTotal Amount:$request->amount$settings->s_currency
     \nDate:$date
     \nAvailable Balance:$bala$settings->s_currency";

        try {

            $account_sid = getenv("TWILIO_SID");
            $auth_token = getenv("TWILIO_TOKEN");
            $twilio_number = getenv("TWILIO_FROM");

            $client = new Client($account_sid, $auth_token);
            $client->messages->create($receiverNumber, [
                'from' => $twilio_number,
                'body' => $message]);



        } catch (Exception $e) {

        }

        }




               }

            } elseif ($request['type'] == "btc_balance") {
    // Convert fiat amount to BTC using live rate
    $btcRate = $this->get_rate('btc', 'usd');
    if ($btcRate <= 0) {
        return redirect()->back()->with('error', 'Unable to get BTC rate. Please try again.');
    }
    
    $fiatAmount = $request['amount']; // Amount admin entered in fiat
    $btcAmount = round($fiatAmount / $btcRate, 8); // Convert to BTC with 8 decimals
    
    $dp = new Withdrawal();
    $dp->amount = $btcAmount; // Store BTC amount, not fiat
    $dp->payment_mode = $request['scope'];
    $dp->Description = ($request['Description'] ?? 'BTC Funding') . " (Rate: $" . number_format($btcRate, 2) . "/BTC)";
    $dp->created_at = $request['date'] ?? now();
    $dp->date = $request['date'] ?? now();
    $dp->type = $request['t_type'];
    $dp->accountname = 'Self';
    $dp->bankname = $settings->site_name;
    $dp->accountnumber = $user->usernumber;
    $dp->Accounttype = $user->accounttype;
    $dp->country = $user->country;
    $dp->bankaddress = $settings->address;
    $dp->status = 'Processed';
    $dp->user = $request['user_id'];
    $dp->txn_id = "$subtxn/$codetxn1-$codetxn2";
    $dp->save();

    User::where('id', $request->user_id)
        ->update([
            'btc_balance' => ($user->btc_balance ?? 0) + $btcAmount,
        ]);

    $bala = number_format(($user->btc_balance ?? 0) + $btcAmount, 8) . ' BTC';

    if($request['notifymailuser'] == 1) {
        $this->sendNotification($user, $request, $dp, $bala, $settings, 'Credit', $fiatAmount, $btcAmount, $btcRate);
    }
        }
        

            //add history
            Tp_Transaction::create([
            'user' => $request->user_id,
            'plan' => "Credit",
            'amount'=>$request->amount,
            'type'=>$request->type,
            ]);

        }
        
        
        
        elseif($request['t_type']=="Debit") {
          if ($request['type']=="Bonus") {
            User::where('id', $request['user_id'])
              ->update([
                'bonus'=> $user_bonus - $request['amount'],
                'account_bal'=> $user_bal - $request->amount,
              ]);
          }elseif ($request['type']=="Profit") {
              User::where('id', $request->user_id)
                ->update([
                  'roi'=> $user_roi - $request->amount,
                  'account_bal'=> $user_bal - $request->amount,
                ]);
            }elseif($request['type']=="Ref_Bonus"){
              User::where('id', $request->user_id)
                ->update([
                  'Ref_Bonus'=> $user_Ref - $request->amount,
                  'account_bal'=> $user_bal - $request->amount,
                ]);
            }
            elseif($request['type']=="balance"){
                $bal =$user_bal - $request->amount;
                $dp=new Withdrawal();
                $dp->amount= $request['amount'];
                $dp->payment_mode = $request['scope'];
                $dp->Description = $request['Description'];
                $dp->created_at  = $request['date'];
                $dp->type    = $request['t_type'];
                $dp->accountname = $request['name'];
                $dp->status= 'Processed';
                $dp->user= $request['user_id'];
                 $dp->bankname = $request['bank'];
              $dp->accountnumber = $request['account_number'];
                $dp->Accounttype = $user->Accountype;
                $dp->country  = $user->country;
                $dp->bankaddress = $request['bankaddress'];
                $dp->txn_id = $txn_id ;
                $dp->bal = $bal ;
                $dp->save();

                User::where('id', $request->user_id)
                  ->update([
                    'account_bal'=> $user_bal - $request->amount,
                  ]);

                $bala =  $user_bal - $request->amount;
                  if($request['notifymailuser']==1){
                    // send notification
            $settings=Settings::where('id', '=', '1')->first();
            $date  = Carbon::parse($dp->created_at)->toDayDateTimeString();
           $message = "Your account have been Debited.
           \r\nDetails of the transaction are shown below;
           \r\nAccount Number: $user->usernumber
           \r\nAccount Name: $user->name $user->middlename $user->lastname
            \r\nDescription: $request->Description
            \r\nTotal Amount: $request->amount$settings->s_currency
            \r\nDate: $date
            \r\nAvailable Balance: $bala$settings->s_currency";
           $subject ="Debit alert Notification[$request->amount$settings->s_currency]";
           Mail::to( $user->email)->send(new NewNotification($message, $subject, $user->name ));


           if($settings->sms=='1'){
               $receiverNumber = $user->phone;
           $message = "Your account have been Debited.
           \nDetails of the transaction are shown below;
        \nAccount Number: $dp->accountnumber
        \nAccount Name: $user->name $user->middlename $user->lastname
        \nDescription: $request->Description
        \nTotal Amount: $request->amount$settings->s_currency
        \nDate:$date
        \nAvailable Balance:$bala$settings->s_currency";

           try {

               $account_sid = getenv("TWILIO_SID");
               $auth_token = getenv("TWILIO_TOKEN");
               $twilio_number = getenv("TWILIO_FROM");

               $client = new Client($account_sid, $auth_token);
               $client->messages->create($receiverNumber, [
                   'from' => $twilio_number,
                   'body' => $message]);



           } catch (Exception $e) {

           }

           }




                  }


              }elseif($request['type'] == "btc_balance") {
    // Convert fiat amount to BTC using live rate
    $btcRate = $this->get_rate('btc', 'usd');
    if ($btcRate <= 0) {
        return redirect()->back()->with('error', 'Unable to get BTC rate. Please try again.');
    }
    
    $fiatAmount = $request['amount']; // Amount admin entered in fiat
    $btcAmount = round($fiatAmount / $btcRate, 8); // Convert to BTC with 8 decimals
    
    $dp = new Withdrawal();
    $dp->amount = $btcAmount; // Store BTC amount, not fiat
    $dp->payment_mode = $request['scope'];
    $dp->Description = ($request['Description'] ?? 'BTC Debit') . " (Rate: $" . number_format($btcRate, 2) . "/BTC)";
    $dp->created_at = $request['date'] ?? now();
    $dp->date = $request['date'] ?? now();
    $dp->type = $request['t_type'];
    $dp->accountname = 'Self';
    $dp->status = 'Processed';
    $dp->user = $request['user_id'];
    $dp->bankname = $settings->bankname;
    $dp->accountnumber = $user->usernumber;
    $dp->Accounttype = $user->Accountype;
    $dp->country = $user->country;
    $dp->bankaddress = $settings->address;
    $dp->txn_id = "$subtxn/$codetxn1-$codetxn2";
    $dp->save();

    User::where('id', $request->user_id)
        ->update([
            'btc_balance' => ($user->btc_balance ?? 0) - $btcAmount,
        ]);

    $bala = number_format(($user->btc_balance ?? 0) - $btcAmount, 8) . ' BTC';

    if($request['notifymailuser'] == 1) {
        $this->sendNotification($user, $request, $dp, $bala, $settings, 'Debit', $fiatAmount, $btcAmount, $btcRate);
    }
}

             //add history
            Tp_Transaction::create([
                'user' => $request->user_id,
                'plan' => "Credit reversal",
                'amount'=>$request->amount,
                'type'=>$request->type,
            ]);

        }
        return redirect()->back()->with('success', 'Action Successful!');
    }



    public function generateTransactions(Request $request)
{
    // Validate admin input
    $request->validate([

        'from_date' => 'required|date',
        'to_date' => 'required|date|after_or_equal:from_date',
        'number_of_transactions' => 'required|integer|min:1',
        'min_amount' => 'required|numeric|min:0',
        'max_amount' => 'required|numeric|gt:min_amount',
        'user_id' => 'required|exists:users,id',
        'type' => 'required|in:credit,debit', // ✅ Validate transaction type
    ]);

    // Retrieve user and settings
    $user = User::find($request->user_id);

    $settings = Settings::where('id', '1')->first();
        $user_bal=$user->account_bal;

        $bank_name = strtoupper(substr($settings->site_name, 0, 3)); // Get first 3 letters
    $txn_id = $bank_name . '-' . substr(md5(uniqid(mt_rand(), true)), 0, 10);

    // International banks
    $banks = [
        ['name' => 'JPMorgan Chase', 'address' => '270 Park Avenue, New York, NY, USA'],
        ['name' => 'Bank of America', 'address' => '100 N Tryon St, Charlotte, NC, USA'],
        ['name' => 'HSBC', 'address' => '8 Canada Square, London, UK'],
        ['name' => 'Barclays', 'address' => '1 Churchill Place, London, UK'],
        ['name' => 'Deutsche Bank', 'address' => 'Taunusanlage 12, Frankfurt, Germany'],
        ['name' => 'Standard Chartered', 'address' => '1 Basinghall Avenue, London, UK'],
        ['name' => 'Citibank', 'address' => '388 Greenwich Street, New York, NY, USA'],
        ['name' => 'UBS', 'address' => 'Bahnhofstrasse 45, Zurich, Switzerland'],
        ['name' => 'Santander', 'address' => 'Av. de Cantabria, Boadilla del Monte, Spain'],
        ['name' => 'Wells Fargo', 'address' => '420 Montgomery Street, San Francisco, CA, USA']
    ];
    // Realistic names
    $names = [
        'Alexander Hamilton', 'Sophia Laurent', 'Liam Bennett', 'Isabella Carter',
        'Ethan Montgomery', 'Amelia Sinclair', 'Noah Fitzgerald', 'Charlotte Windsor',
        'Benjamin Harrington', 'Olivia Kensington'
    ];

    // Payment modes
    $payment_modes = ['Local Transfer', 'International Transfer', 'Crypto Deposit', 'Check Deposit'];

    // Random descriptions
    $descriptions = [
        'Payment for services rendered', 'Invoice settlement', 'Salary payment',
        'Online purchase refund', 'Loan repayment', 'Investment deposit',
        'Transfer to savings account', 'Bill payment', 'Cryptocurrency exchange',
        'Payment received from client'
    ];
    $transaction_types = ['Credit', 'Debit']; // Now random
    
    $selected_type = strtolower($request->type); // ✅ Get selected type ('credit' or 'debit')

    for ($i = 0; $i < $request->number_of_transactions; $i++) {
        // Generate random values
        $random_date = Carbon::createFromTimestamp(rand(strtotime($request->from_date), strtotime($request->to_date)));
        $random_amount = rand($request->min_amount * 100, $request->max_amount * 100) / 100;
        $random_payment_mode = $payment_modes[array_rand($payment_modes)];
        $random_name = $names[array_rand($names)];
        $random_bank = $banks[array_rand($banks)];
        $random_account_number = rand(1000000000, 9999999999);
        $transaction_type = $request->transaction_type;
        $random_description = $descriptions[array_rand($descriptions)];
        $transaction_type = $transaction_types[array_rand($transaction_types)];

        // Generate Transaction ID
        $bank_code = strtoupper(substr(str_replace(' ', '', $settings->site_name), 0, 3));
        $txn_id = $txn_id;

        // Calculate new balance
        // $user_balance =  $user_bal;
        // $new_balance = ($transaction_type === 'Debit') ? $user_balance - $random_amount : $user_balance + $random_amount;


// $user_balance = $user_bal;

// // Calculate the new balance based on transaction type
// $new_balance = ($transaction_type === 'Debit') ? $user_balance - $random_amount : $user_balance + $random_amount;

// // Update the user's account balance
// User::where('id', $request['user_id'])
//     ->update([
//         'account_bal' => $new_balance, // Use calculated balance
//     ]);


        // ✅ Calculate new balance based on selected type
        $user_balance = $user_bal;
        if ($selected_type === 'debit') {
            $user_bal -= $random_amount;
        } else {
            $user_bal += $random_amount;
        }

        // ✅ Update balance in DB after final iteration (only once, OR inside loop if needed per tx)
        User::where('id', $request['user_id'])->update([
            'account_bal' => $user_bal,
        ]);


        // Create transaction record
        $transaction = new Withdrawal();
        $transaction->amount = $random_amount;
        $transaction->payment_mode = $random_payment_mode;
        $transaction->Description = $random_description;
        $transaction->created_at = $random_date;
        $transaction->type = ucfirst($selected_type);
        $transaction->accountname = $random_name;
        $transaction->bankname = $random_bank['name'];
        $transaction->accountnumber = $random_account_number;
        $transaction->Accounttype = "Online Banking";
        $transaction->country = $user->country;
        $transaction->bankaddress = $random_bank['address']; // ✅ Random Bank Address
        $transaction->status = 'Processed';
        $transaction->user = $request->user_id;
        $transaction->txn_id = $txn_id;
        $transaction->bal = $new_balance;
        $transaction->save();
    }

    return back()->with('success', "{$request->number_of_transactions} transactions generated successfully.");
}



private function sendNotification($user, $request, $dp, $balance, $settings, $type)
{
    $date = Carbon::parse($dp->created_at)->toDayDateTimeString();
    $message = "Your account have been {$type}ed.\r\nDetails of the transaction are shown below;\r\nAccount Number: $user->usernumber\r\nAccount Name: $user->name $user->middlename $user->lastname\r\nDescription: $request->Description\r\nTotal Amount: $request->amount$settings->s_currency\r\nDate: $date\r\nAvailable Balance: $balance";
    
    $subject = "{$type} alert Notification[$request->amount$settings->s_currency]";
    Mail::to($user->email)->send(new NewNotification($message, $subject, $user->name));

    if($settings->sms == '1') {
        $receiverNumber = $user->phone;
        $smsMessage = "Your account have been {$type}ed.\nDetails:\nAccount: $dp->accountnumber\nName: $user->name $user->middlename $user->lastname\nDesc: $request->Description\nAmount: $request->amount$settings->s_currency\nDate: $date\nBalance: $balance";
        
        try {
            $account_sid = getenv("TWILIO_SID");
            $auth_token = getenv("TWILIO_TOKEN");
            $twilio_number = getenv("TWILIO_FROM");

            $client = new Client($account_sid, $auth_token);
            $client->messages->create($receiverNumber, [
                'from' => $twilio_number, 
                'body' => $smsMessage
            ]);
        } catch (Exception $e) {
            // Handle exception
        }
    }
}
function RandomStringGenerator($n)
    {
        $generated_string = "";
        $domain = "ABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
        $len = strlen($domain);
        for ($i = 0; $i < $n; $i++) {
            $index = rand(0, $len - 1);
            $generated_string = $generated_string . $domain[$index];
        }
        // Return the random generated string 
        return $generated_string;
    }


}
