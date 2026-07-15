<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Settings;
use App\Models\Deposit;
use App\Models\Wdmethod;
use App\Models\Tp_Transaction;
use App\Helpers\NotificationHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Mail\DepositStatus;
use Session;
use Carbon\Carbon;
use Twilio\Rest\Client;
use Exception;

class DepositController extends Controller
{
    /**
     * Get payment method details
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getmethod($id)
    {
        try {
            $methodname = Wdmethod::where('id', $id)->first();
            
            if (!$methodname) {
                return response()->json(['error' => 'Payment method not found'], 404);
            }
            
            return response()->json($methodname->name);
        } catch (Exception $e) {
            Log::error('Error in getmethod: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred'], 500);
        }
    }

    /**
     * Process new deposit request
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function newdeposit(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->with('message', 'Please provide valid deposit details.')
                ->withErrors($validator);
        }

        try {
            $settings = $this->getSettings();
            $methodname = Wdmethod::where('name', $request->payment_method)->first();

            if (!$methodname) {
                return redirect()->back()
                    ->with('message', 'Invalid payment method selected.');
            }

            $client_secret = null;

            // Handle Stripe payment
            if ($methodname->name == "Credit Card" && $settings->credit_card_provider == "Stripe") {
                // Validate Stripe keys are set
                if (empty($settings->s_s_k)) {
                    return redirect()->back()
                        ->with('message', 'Payment configuration error. Please contact support.');
                }

                try {
                    $secretkey = $settings->s_s_k;
                    $zero = '00';
                    $amt = $request->amount . $zero;

                    \Stripe\Stripe::setApiKey($secretkey);
                    $paymentIntent = \Stripe\PaymentIntent::create([
                        'amount' => (int)$amt, // Ensure amount is an integer
                        'currency' => strtolower($settings->s_currency),
                        'payment_method_types' => ['card'],
                        'description' => 'Funding My Investment Account',
                        'shipping' => [
                            'name' => Auth::user()->name,
                            'address' => [
                                'line1' => 'No Address',
                                'postal_code' => '000000',
                                'city' => 'No City',
                                'state' => 'CA',
                                'country' => 'US',
                            ],
                        ],
                        'metadata' => ['integration_check' => 'accept_a_payment'],
                    ]);

                    $client_secret = $paymentIntent->client_secret;
                } catch (\Stripe\Exception\ApiErrorException $e) {
                    Log::error('Stripe error: ' . $e->getMessage());
                    return redirect()->back()
                        ->with('message', 'Payment processing error: ' . $e->getMessage());
                }
            }

            // Store payment info in session
            $request->session()->put('amount', $request->amount);
            $request->session()->put('payment_mode', $methodname->name);
            $request->session()->put('intent', $client_secret);

            return redirect()->route('payment');
        } catch (Exception $e) {
            Log::error('Error in newdeposit: ' . $e->getMessage());
            return redirect()->back()
                ->with('message', 'An error occurred while processing your deposit request.');
        }
    }

    /**
     * Show payment interface
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function payment(Request $request)
    {
        try {
            $paymentMode = $request->session()->get('payment_mode');
            $amount = $request->session()->get('amount');
            $intent = $request->session()->get('intent');

            if (!$paymentMode || !$amount) {
                return redirect()->route('deposits')
                    ->with('message', 'Payment session expired. Please start again.');
            }

            $methodname = Wdmethod::where('name', $paymentMode)->first();
            
            if (!$methodname) {
                return redirect()->route('deposits')
                    ->with('message', 'Invalid payment method. Please try again.');
            }

            return view('user.payment')
                ->with([
                    'amount' => $amount,
                    'payment_mode' => $methodname,
                    'intent' => $intent,
                    'title' => 'Make Payment',
                ]);
        } catch (Exception $e) {
            Log::error('Error in payment: ' . $e->getMessage());
            return redirect()->route('deposits')
                ->with('message', 'An error occurred while loading the payment page.');
        }
    }

    /**
     * Save Stripe payment
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function savestripepayment(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid amount'], 422);
        }

        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'User authentication failed'], 401);
            }

            $settings = $this->getSettings();
            
            // Process referral commission if applicable
            $earnings = 0;
            if ($settings->referral_commission > 0) {
                $earnings = $settings->referral_commission * $request->amount / 100;
            }

            // Save and confirm the deposit
            $dp = new Deposit();
            $dp->amount = $request->amount;
            $dp->payment_mode = "Stripe";
            $dp->status = 'Processed';
            $dp->proof = "Credit Card";
            $dp->plan = 0;
            $dp->user = $user->id;
            $dp->save();

            // Process deposit bonus if applicable
            $bonus = 0;
            if ($settings->deposit_bonus != null && $settings->deposit_bonus > 0) {
                $bonus = $request->amount * $settings->deposit_bonus / 100;
                
                // Create transaction record for bonus
                // Use parent method with a properly formatted request
                $transactionData = [
                    'user' => $user->id,
                    'plan' => "Deposit Bonus for {$this->getCurrencySymbol($user, $settings)} {$request->amount} deposited",
                    'amount' => $bonus,
                    'type' => "Bonus"
                ];
                $this->CreateTransaction((object)$transactionData);
            }

            // Add funds to user's account
            User::where('id', $user->id)
                ->update([
                    'account_bal' => $user->account_bal + $request->amount + $bonus,
                    'bonus' => $user->bonus + $bonus,
                    'cstatus' => 'Customer',
                ]);

            // Process referral bonus if applicable
            if (!empty($user->ref_by)) {
                $this->processReferralBonus($user, $request->amount, $earnings, $settings);
            }

            // Send confirmation email
            $this->sendDepositEmail($dp, $user, 'Successful Deposit', false);

            // Delete session variables
            $request->session()->forget(['payment_mode', 'amount', 'intent']);

            return response()->json(['success' => 'Payment Completed, redirecting']);
        } catch (Exception $e) {
            Log::error('Error in savestripepayment: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while processing your payment'], 500);
        }
    }

    /**
     * Save deposit request
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function savedeposit(Request $request)
    {
        try {
            $user = Auth::user();
            $settings = $this->getSettings();

            // Check if account is active
            if ($user->account_status != 'active') {
                return redirect()->back()
                    ->with("message", "Sorry, your account is dormant. Contact support on {$settings->contact_email} for more details.");
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:1',
                'paymethd_method' => 'required|string',
                'proof' => 'required|image|mimes:jpg,jpeg,png|max:1000',
            ]);

            if ($validator->fails()) {
                return redirect()->back()
                    ->with('message', 'Please provide valid deposit details.')
                    ->withErrors($validator);
            }

            $path = null;
            // Process uploaded proof
            if ($request->hasFile('proof')) {
                $file = $request->file('proof');
                $extension = $file->getClientOriginalExtension();
                $whitelist = ['pdf', 'doc', 'jpeg', 'jpg', 'png'];

                if (!in_array($extension, $whitelist)) {
                    return redirect()->back()
                        ->with('message', 'Unaccepted file type uploaded. Allowed types: PDF, DOC, JPEG, JPG, PNG');
                }

                // Store the file
                $path = $file->store('uploads', 'public');
            }

            // Generate Reference ID
            $subtxn = substr(strtoupper($settings->site_name), 0, 4);
            $codetxn1 = $this->RandomStringGenerator(8);
            $codetxn2 = substr(strtoupper(Carbon::now()), 0, 4);
            $txnId = "{$subtxn}/{$codetxn1}-{$codetxn2}";

            // Save deposit
            $dp = new Deposit();
            $dp->amount = $request->amount;
            $dp->payment_mode = $request->paymethd_method;
            $dp->status = 'Pending';
            $dp->proof = $path;
            $dp->txn_id = $txnId;
            $dp->user = $user->id;
            $dp->save();

            // Create notification for deposit
            $this->createDepositNotification($user, $request->amount, $request->paymethd_method, $settings);

            // Send email notifications
            $this->sendDepositEmail($dp, $user, 'Successful Deposit', true);
            $this->sendDepositEmail($dp, $user, 'Successful Deposit', false);

            // Send SMS notification if enabled
            if ($settings->sms == '1') {
                $this->sendDepositSMS($user, $dp, $settings);
            }

            // Kill the session variables
            $request->session()->forget(['payment_mode', 'amount']);

            return redirect()->route('deposits')
                ->with('success', 'Account Fund Successful! Please wait for system to validate this transaction.');
        } catch (Exception $e) {
            Log::error('Error in savedeposit: ' . $e->getMessage());
            return redirect()->back()
                ->with('message', 'An error occurred while processing your deposit: ' . $e->getMessage());
        }
    }

    /**
     * Process referral bonus
     *
     * @param User $user
     * @param float $amount
     * @param float $earnings
     * @param Settings $settings
     * @return void
     */
    private function processReferralBonus($user, $amount, $earnings, $settings)
    {
        try {
            $agent = User::where('id', $user->ref_by)->first();
            
            if (!$agent) {
                return;
            }

            // Update referrer's balances
            User::where('id', $user->ref_by)
                ->update([
                    'account_bal' => $agent->account_bal + $earnings,
                    'ref_bonus' => $agent->ref_bonus + $earnings,
                ]);

            // Create transaction record using parent method
            $transactionData = [
                'user' => $user->ref_by,
                'plan' => "Credit",
                'amount' => $earnings,
                'type' => "Ref_bonus"
            ];
            $this->CreateTransaction((object)$transactionData);

            // Credit commission to ancestors
            $users = User::all();
            $this->getAncestors($users, $amount, $user->id);
        } catch (Exception $e) {
            Log::error('Error processing referral bonus: ' . $e->getMessage());
            // Continue execution even if referral processing fails
        }
    }

    /**
     * Create notification for deposit
     *
     * @param User $user
     * @param float $amount
     * @param string $method
     * @param Settings $settings
     * @return void
     */
    private function createDepositNotification($user, $amount, $method, $settings)
    {
        try {
            $currency = $this->getCurrencySymbol($user, $settings);
            
            NotificationHelper::create(
                $user,
                "Your deposit of {$currency}{$amount} via {$method} has been received and is pending approval.",
                'Deposit Submitted',
                'info',
                'upload',
                route('deposits')
            );
        } catch (Exception $e) {
            Log::error('Error creating deposit notification: ' . $e->getMessage());
            // Continue execution even if notification fails
        }
    }

    /**
     * Send deposit confirmation email
     *
     * @param Deposit $deposit
     * @param User $user
     * @param string $subject
     * @param bool $isAdmin
     * @return void
     */
    private function sendDepositEmail($deposit, $user, $subject, $isAdmin)
    {
        try {
            $settings = $this->getSettings();
            $recipient = $isAdmin ? $settings->contact_email : $user->email;
            
            Mail::to($recipient)->send(new DepositStatus($deposit, $user, $subject, $isAdmin));
        } catch (Exception $e) {
            Log::error('Error sending deposit email: ' . $e->getMessage());
            // Continue execution even if email sending fails
        }
    }

    /**
     * Send SMS notification for deposit
     *
     * @param User $user
     * @param Deposit $dp
     * @param Settings $settings
     * @return void
     */
    private function sendDepositSMS($user, $dp, $settings)
    {
        try {
            $receiverNumber = $user->phone;
            
            if (empty($receiverNumber)) {
                return;
            }
            
            $date = Carbon::parse($dp->created_at)->toDayDateTimeString();
            $currency = $this->getCurrencySymbol($user, $settings);
            
            $message = "Your Crypto Asset Deposit has been recorded successfully and currently undergoing confirmation. You will receive an automatic notification once your transaction was confirmed on the blockchain Network. This usually take upto 15 minutes." .
                "\n Amount : {$currency}{$dp->amount}" .
                "\n Payment method : {$dp->payment_mode}" .
                "\n Date: {$date}";

            $account_sid = getenv("TWILIO_SID");
            $auth_token = getenv("TWILIO_TOKEN");
            $twilio_number = getenv("TWILIO_FROM");

            if (empty($account_sid) || empty($auth_token) || empty($twilio_number)) {
                Log::warning('Twilio credentials not properly configured');
                return;
            }

            $client = new Client($account_sid, $auth_token);
            $client->messages->create($receiverNumber, [
                'from' => $twilio_number,
                'body' => $message
            ]);
        } catch (Exception $e) {
            Log::error('Error sending SMS: ' . $e->getMessage());
            // Continue execution even if SMS sending fails
        }
    }

    /**
     * Get ancestors for referral system
     *
     * @param array $array
     * @param float $depositAmount
     * @param int $parent
     * @param int $level
     * @return string
     */
    function getAncestors($array, $depositAmount, $parent = 0, $level = 0)
    {
        $referedMembers = '';
        $parent = User::where('id', $parent)->first();
        
        if (!$parent) {
            return $referedMembers;
        }

        try {
            foreach ($array as $entry) {
                if ($entry->id == $parent->ref_by) {
                    $settings = $this->getSettings();
                    $commissionKey = "referral_commission" . ($level > 0 ? $level : '');
                    
                    if (isset($settings->$commissionKey) && $settings->$commissionKey > 0) {
                        $earnings = $settings->$commissionKey * $depositAmount / 100;
                        
                        // Update ancestor's balance
                        User::where('id', $entry->id)
                            ->update([
                                'account_bal' => $entry->account_bal + $earnings,
                                'ref_bonus' => $entry->ref_bonus + $earnings,
                            ]);

                        // Create transaction record using parent method
                        $transactionData = [
                            'user' => $entry->id,
                            'plan' => "Credit",
                            'amount' => $earnings,
                            'type' => "Ref_bonus"
                        ];
                        $this->CreateTransaction((object)$transactionData);
                    }

                    if ($level == 6) {
                        break;
                    }

                    $referedMembers .= $this->getAncestors($array, $depositAmount, $entry->id, $level + 1);
                }
            }
        } catch (Exception $e) {
            Log::error('Error in getAncestors: ' . $e->getMessage());
        }
        
        return $referedMembers;
    }

    /**
     * Get currency symbol from user or settings
     *
     * @param User $user
     * @param Settings $settings
     * @return string
     */
    private function getCurrencySymbol($user, $settings)
    {
        return isset($user->s_curr) ? $user->s_curr : $settings->currency;
    }

    /**
     * Get settings with error handling
     *
     * @return Settings
     */
    private function getSettings()
    {
        $settings = Settings::first();
        if (!$settings) {
            $settings = new Settings();
            $settings->site_name = 'Investment System';
            $settings->save();
        }
        return $settings;
    }

    /**
     * Generate random string for codes and references
     *
     * @param int $length
     * @return string
     */
    private function RandomStringGenerator($length)
    {
        $generated_string = "";
        $domain = "ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
        $len = strlen($domain);
        
        for ($i = 0; $i < $length; $i++) {
            $index = rand(0, $len - 1);
            $generated_string .= $domain[$index];
        }
        
        return $generated_string;
    }
}