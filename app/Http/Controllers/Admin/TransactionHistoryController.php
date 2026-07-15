<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Settings;
use App\Models\Withdrawal;
use App\Mail\NewNotification;
use App\Helpers\NotificationHelper;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TransactionHistoryController extends Controller
{
    /**
     * Show the form for generating transaction history
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function showForm()
    {
        $users = User::all();
        $settings = Settings::where('id', '=', '1')->first();
        
        return view('admin.transaction-history.generate', [
            'users' => $users,
            'settings' => $settings,
            'title' => 'Generate Transaction History',
        ]);
    }
    
    /**
     * Generate new transaction history entries in bulk
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function generateHistory(Request $request)
    {
        // Validate the request
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'num_transactions' => 'required|integer|min:1|max:50',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gte:min_amount',
            'payment_mode' => 'required|string',
            'transaction_type' => 'required|in:Credit,Debit',
            'status' => 'required|in:Processed,Pending',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);
        
        // Get user and settings
        $user = User::findOrFail($request->user_id);
        
        // Convert dates to Carbon instances
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        
        // Calculate the total time span in seconds
        $timeSpanInSeconds = $endDate->diffInSeconds($startDate);
        
        // Generate multiple transactions
        for ($i = 0; $i < $request->num_transactions; $i++) {
            // Generate random amount between min and max
            $amount = mt_rand($request->min_amount * 100, $request->max_amount * 100) / 100;
            
            // Generate random timestamp between start and end date
            $randTimeOffset = mt_rand(0, $timeSpanInSeconds);
            $transactionDate = $startDate->copy()->addSeconds($randTimeOffset);
            
            // Generate transaction ID based on type (GLOB for domestic, INTE for international)
            $prefix = $request->payment_mode === 'International Wire Transfer' ? 'INTE/' : 'GLOB/';
            $random_str = strtoupper(Str::random(8));
            $txn_id = $prefix . $random_str . '-' . $transactionDate->format('Y');
            
            // Generate random description
            $descriptions = [
                'Credit' => [
                    'Deposit via ' . $request->payment_mode,
                    'Funds received through ' . $request->payment_mode,
                    'Payment received',
                    'Account credit',
                    'Incoming transfer'
                ],
                'Debit' => [
                    'Withdrawal via ' . $request->payment_mode,
                    'Payment sent',
                    'Funds transfer',
                    'Account debit',
                    'Outgoing transfer'
                ]
            ];
            
            $descriptionIndex = array_rand($descriptions[$request->transaction_type]);
            $description = $descriptions[$request->transaction_type][$descriptionIndex];
            
            // Create new withdrawal record
            $withdrawal = new Withdrawal();
            $withdrawal->txn_id = $txn_id;
            $withdrawal->user = $request->user_id;
            $withdrawal->amount = $amount;
            $withdrawal->description = $description;
            $withdrawal->payment_mode = $request->payment_mode;
            $withdrawal->type = $request->transaction_type;
            $withdrawal->status = $request->status;
            $withdrawal->created_at = $transactionDate;
            $withdrawal->date = $transactionDate;
            $withdrawal->updated_at = Carbon::now();
            
            // Set payment-specific mock data based on the payment method
            $this->setPaymentData($withdrawal, $request->payment_mode);
            
            $withdrawal->save();
        }
        
        return redirect()->route('transaction.history')->with('success', $request->num_transactions . ' transaction history entries generated successfully!');
    }
    
    /**
     * Set mock payment data based on payment method
     *
     * @param  \App\Models\Withdrawal  $withdrawal
     * @param  string  $paymentMode
     * @return void
     */
    private function setPaymentData(Withdrawal $withdrawal, $paymentMode)
    {
        switch($paymentMode) {
            case 'International Wire Transfer':
                $banks = [
                    'Chase Bank', 'Bank of America', 'Wells Fargo', 'Citibank', 'HSBC', 'Barclays',
                    'Deutsche Bank', 'BNP Paribas', 'Santander', 'UBS', 'Credit Suisse', 'Standard Chartered',
                    'Royal Bank of Canada', 'Mizuho Bank', 'Sumitomo Mitsui Banking', 'DBS Bank', 'ANZ Bank',
                    'Société Générale', 'UniCredit Bank', 'Intesa Sanpaolo', 'Nordea Bank', 'Commerzbank'
                ];
                
                $countries = [
                    'United States', 'United Kingdom', 'Germany', 'Canada', 'Australia', 'Japan',
                    'France', 'Spain', 'Italy', 'Switzerland', 'Netherlands', 'Singapore', 'Sweden',
                    'Norway', 'Denmark', 'Finland', 'Austria', 'Belgium', 'Portugal', 'Ireland',
                    'Luxembourg', 'New Zealand', 'United Arab Emirates', 'Hong Kong', 'South Korea'
                ];
                
                $cities = [
                    'New York', 'London', 'Frankfurt', 'Toronto', 'Sydney', 'Tokyo', 'Paris', 'Madrid',
                    'Rome', 'Zurich', 'Amsterdam', 'Singapore', 'Stockholm', 'Oslo', 'Copenhagen',
                    'Helsinki', 'Vienna', 'Brussels', 'Lisbon', 'Dublin', 'Luxembourg City', 'Auckland',
                    'Dubai', 'Hong Kong', 'Seoul', 'Geneva', 'Munich', 'Milan', 'Barcelona', 'Montreal'
                ];
                
                $accountHolderPrefixes = [
                    'Account Holder', 'Client', 'Customer', 'User', 'Member', 'Beneficiary',
                    'Account Owner', 'Account User', 'Primary Holder', 'Primary User'
                ];
                
                $withdrawal->accountname = $accountHolderPrefixes[array_rand($accountHolderPrefixes)] . ' ' . Str::random(4);
                $withdrawal->accountnumber = mt_rand(1000000000, 9999999999);
                $withdrawal->bankname = $banks[array_rand($banks)];
                $withdrawal->bankaddress = mt_rand(1, 999) . ' ' . Str::random(6) . ' St, ' . $cities[array_rand($cities)];
                $withdrawal->swiftcode = strtoupper(Str::random(8));
                $withdrawal->country = $countries[array_rand($countries)];
                $withdrawal->iban = strtoupper(Str::random(2)) . mt_rand(10, 99) . ' ' . strtoupper(Str::random(4)) . ' ' . mt_rand(1000, 9999) . ' ' . mt_rand(1000, 9999) . ' ' . mt_rand(10, 99);
                $withdrawal->Accounttype = $this->getRandomAccountType('international');
                break;
                
            case 'Domestic Transfer':
                $banks = [
                    'Chase Bank', 'Bank of America', 'Wells Fargo', 'Citibank', 'Capital One', 'TD Bank',
                    'PNC Bank', 'U.S. Bank', 'Truist Bank', 'Fifth Third Bank', 'KeyBank', 'Citizens Bank',
                    'Regions Bank', 'Huntington National Bank', 'Ally Bank', 'USAA', 'Navy Federal Credit Union',
                    'Charles Schwab Bank', 'SunTrust Bank', 'M&T Bank', 'First Republic Bank', 'BMO Harris'
                ];
                
                $userPrefixes = [
                    'Domestic User', 'Personal Account', 'Individual Client', 'Customer', 'Member',
                    'Private Client', 'Individual', 'Personal User', 'Primary Account', 'Retail Client'
                ];
                
                $withdrawal->accountname = $userPrefixes[array_rand($userPrefixes)] . ' ' . Str::random(4);
                $withdrawal->accountnumber = mt_rand(1000000000, 9999999999);
                $withdrawal->bankname = $banks[array_rand($banks)];
                $withdrawal->Accounttype = $this->getRandomAccountType('domestic');
                break;
                
            case 'Cryptocurrency':
                $cryptos = [
                    'BTC', 'ETH', 'USDT', 'XRP', 'SOL', 'ADA', 'USDC', 'DOT', 'AVAX', 'DOGE',
                    'LINK', 'LTC', 'MATIC', 'UNI', 'ALGO', 'XLM', 'ATOM', 'VET', 'NEAR', 'FTM',
                    'HBAR', 'TRX', 'FIL', 'XTZ', 'BCH', 'ETC', 'MANA', 'SAND', 'AAVE', 'APE'
                ];
                
                $networks = [
                    'BTC Network', 'ERC20', 'BEP20', 'TRC20', 'Polygon', 'Solana', 'Avalanche C-Chain',
                    'Arbitrum', 'Optimism', 'Fantom', 'Harmony', 'Moonbeam', 'Cosmos', 'Polkadot',
                    'Cardano', 'XRP Ledger', 'Stellar', 'Algorand', 'NEAR Protocol', 'Bitcoin Cash',
                    'Litecoin', 'Tezos', 'Hedera'
                ];
                
                $addressPrefixes = [
                    '0x', 'bc1', '1', '3', 'bnb', 'addr1', 'cosmos', 'terra1', 'osmo1', 'xrp',
                    'GCKA', 'tz1', 'DJT', 'nano_', 'zil', 'kava', 'bts', 'ltc', 'dgb'
                ];
                
                $selectedCrypto = $cryptos[array_rand($cryptos)];
                $selectedNetwork = $networks[array_rand($networks)];
                
                // Choose appropriate address prefix based on crypto
                $prefix = '0x'; // Default for Ethereum-based
                if ($selectedCrypto === 'BTC') {
                    $prefix = array_rand(['bc1' => 1, '1' => 1, '3' => 1]);
                } elseif ($selectedCrypto === 'SOL') {
                    $prefix = '';
                } elseif ($selectedCrypto === 'ADA') {
                    $prefix = 'addr1';
                } elseif ($selectedCrypto === 'XRP') {
                    $prefix = 'r';
                } else {
                    $prefix = $addressPrefixes[array_rand($addressPrefixes)];
                }
                
                $withdrawal->crypto_currency = $selectedCrypto;
                $withdrawal->crypto_network = $selectedNetwork;
                $withdrawal->wallet_address = $prefix . strtolower(Str::random(40));
                break;
                
            case 'PayPal':
                $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'aol.com', 'icloud.com', 
                            'protonmail.com', 'mail.com', 'zoho.com', 'live.com', 'me.com', 'yandex.com'];
                
                $prefixes = ['user', 'account', 'client', 'personal', 'business', 'payment', 'transfer', 
                             'customer', 'pp', 'pay', 'cashflow', 'finance', 'money', 'wallet'];
                
                $withdrawal->paypal_email = $prefixes[array_rand($prefixes)] . mt_rand(100, 9999) . '@' . $domains[array_rand($domains)];
                break;
                
            case 'Wise Transfer':
                $countries = [
                    'United States', 'United Kingdom', 'Germany', 'Canada', 'Australia', 'Japan',
                    'France', 'Spain', 'Italy', 'Netherlands', 'Belgium', 'Sweden', 'Norway', 'Denmark',
                    'Finland', 'Singapore', 'New Zealand', 'Ireland', 'Portugal', 'Austria', 'Switzerland',
                    'Poland', 'Hungary', 'Czech Republic', 'Romania', 'Greece', 'Turkey', 'Brazil', 'Mexico'
                ];
                
                $firstNames = [
                    'James', 'John', 'Robert', 'Michael', 'William', 'David', 'Richard', 'Joseph', 'Thomas', 'Charles',
                    'Mary', 'Patricia', 'Jennifer', 'Linda', 'Elizabeth', 'Barbara', 'Susan', 'Jessica', 'Sarah',
                    'Karen', 'Emma', 'Olivia', 'Ava', 'Isabella', 'Sophia', 'Mia', 'Charlotte', 'Amelia', 'Harper', 'Evelyn',
                    'Liam', 'Noah', 'Oliver', 'Elijah', 'William', 'James', 'Benjamin', 'Lucas', 'Henry', 'Alexander'
                ];
                
                $lastNames = [
                    'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
                    'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin',
                    'Lee', 'Perez', 'Thompson', 'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson',
                    'Walker', 'Young', 'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores'
                ];
                
                $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'aol.com', 'icloud.com', 
                           'protonmail.com', 'mail.com', 'zoho.com', 'live.com'];
                
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                
                $withdrawal->wise_fullname = $firstName . ' ' . $lastName;
                $withdrawal->wise_email = strtolower($firstName) . '.' . strtolower($lastName) . mt_rand(1, 999) . '@' . $domains[array_rand($domains)];
                $withdrawal->wise_country = $countries[array_rand($countries)];
                break;
                
            case 'Skrill':
                $firstNames = [
                    'James', 'John', 'Robert', 'Michael', 'William', 'David', 'Richard', 'Joseph', 'Thomas', 'Charles',
                    'Mary', 'Patricia', 'Jennifer', 'Linda', 'Elizabeth', 'Barbara', 'Susan', 'Jessica', 'Sarah', 'Karen'
                ];
                
                $lastNames = [
                    'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'
                ];
                
                $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'aol.com', 'icloud.com'];
                
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                
                $withdrawal->skrill_fullname = $firstName . ' ' . $lastName;
                $withdrawal->skrill_email = strtolower($firstName) . '.' . strtolower($lastName) . mt_rand(1, 999) . '@' . $domains[array_rand($domains)];
                break;
                
            case 'Venmo':
                $prefixes = ['@', '$', '.', '-', '_'];
                $venmoNames = [
                    'venmo_user', 'pay_me', 'money_transfer', 'cash_buddy', 'quick_pay', 'easy_money',
                    'fast_cash', 'send_money', 'payment_pal', 'digital_wallet', 'mobile_pay', 'venmo_pro',
                    'pay_friend', 'cash_sender', 'money_mover', 'venmo_master', 'payment_pro', 'venmo_vip'
                ];
                
                $countryCodes = ['1', '44', '61', '64', '33', '49', '81', '86', '91', '52', '55', '27', '82', '7'];
                
                $withdrawal->venmo_username = $prefixes[array_rand($prefixes)] . $venmoNames[array_rand($venmoNames)] . mt_rand(10, 9999);
                $withdrawal->venmo_phone = '+' . $countryCodes[array_rand($countryCodes)] . mt_rand(1000000000, 9999999999);
                break;
                
            case 'Zelle':
                $firstNames = [
                    'James', 'John', 'Robert', 'Michael', 'William', 'David', 'Richard', 'Joseph', 'Thomas', 'Charles',
                    'Mary', 'Patricia', 'Jennifer', 'Linda', 'Elizabeth', 'Barbara', 'Susan', 'Jessica', 'Sarah', 'Karen'
                ];
                
                $lastNames = [
                    'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'
                ];
                
                $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'aol.com', 'icloud.com'];
                
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                
                $withdrawal->zelle_name = $firstName . ' ' . $lastName;
                $withdrawal->zelle_email = strtolower($firstName) . '.' . strtolower($lastName) . mt_rand(1, 999) . '@' . $domains[array_rand($domains)];
                $withdrawal->zelle_phone = '+1' . mt_rand(2000000000, 9999999999);
                break;
                
            case 'Cash App':
                $cashAppSymbols = ['$', '£', '€', '¥', '₹', '₽', '₿', '₴', '₩', '₫'];
                $cashAppNames = [
                    'CashApp', 'MoneySquare', 'QuickCash', 'PayFast', 'CashMoney', 'SquarePay',
                    'FastMoney', 'CashPro', 'PayPal', 'MoneyBag', 'CashFlow', 'MoneyMaker',
                    'CashKing', 'DigitalCash', 'PayMaster', 'CashSend', 'MoneyTransfer', 'CashWave'
                ];
                
                $firstNames = [
                    'James', 'John', 'Robert', 'Michael', 'William', 'David', 'Richard', 'Joseph', 'Thomas', 'Charles',
                    'Mary', 'Patricia', 'Jennifer', 'Linda', 'Elizabeth', 'Barbara', 'Susan', 'Jessica', 'Sarah', 'Karen'
                ];
                
                $lastNames = [
                    'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'
                ];
                
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                
                $withdrawal->cash_app_tag = $cashAppSymbols[array_rand($cashAppSymbols)] . $cashAppNames[array_rand($cashAppNames)] . mt_rand(10, 9999);
                $withdrawal->cash_app_fullname = $firstName . ' ' . $lastName;
                break;
                
            case 'Revolut':
                $firstNames = [
                    'James', 'John', 'Robert', 'Michael', 'William', 'David', 'Richard', 'Joseph', 'Thomas', 'Charles',
                    'Mary', 'Patricia', 'Jennifer', 'Linda', 'Elizabeth', 'Barbara', 'Susan', 'Jessica', 'Sarah', 'Karen',
                    'Emma', 'Olivia', 'Ava', 'Isabella', 'Sophia', 'Mia', 'Charlotte', 'Amelia', 'Harper', 'Evelyn'
                ];
                
                $lastNames = [
                    'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
                    'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin'
                ];
                
                $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'aol.com', 'icloud.com',
                           'protonmail.com', 'mail.com', 'zoho.com', 'live.com'];
                
                $countryCodes = ['1', '44', '61', '64', '33', '49', '81', '86', '91', '52', '55', '27', '82', '7', '39', '34', '31', '46', '47', '45'];
                
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                
                $withdrawal->revolut_fullname = $firstName . ' ' . $lastName;
                $withdrawal->revolut_email = strtolower($firstName) . '.' . strtolower($lastName) . mt_rand(1, 999) . '@' . $domains[array_rand($domains)];
                $withdrawal->revolut_phone = '+' . $countryCodes[array_rand($countryCodes)] . mt_rand(1000000000, 9999999999);
                break;
                
            case 'Alipay':
                $chineseLastNames = [
                    'Wang', 'Li', 'Zhang', 'Liu', 'Chen', 'Yang', 'Huang', 'Zhao', 'Wu', 'Zhou',
                    'Sun', 'Ma', 'Zhu', 'Hu', 'Guo', 'Lin', 'He', 'Gao', 'Luo', 'Zheng'
                ];
                
                $chineseFirstNames = [
                    'Wei', 'Jing', 'Yan', 'Ming', 'Hui', 'Xin', 'Yu', 'Jun', 'Ying', 'Tao',
                    'Yong', 'Hao', 'Fei', 'Xiuying', 'Na', 'Zhiqiang', 'Lei', 'Min', 'Xuan', 'Bin'
                ];
                
                $firstName = $chineseFirstNames[array_rand($chineseFirstNames)];
                $lastName = $chineseLastNames[array_rand($chineseLastNames)];
                
                $withdrawal->alipay_id = 'alipay' . mt_rand(100000, 999999);
                $withdrawal->alipay_fullname = $lastName . ' ' . $firstName;
                break;
                
            case 'WeChat Pay':
                $chineseLastNames = [
                    'Wang', 'Li', 'Zhang', 'Liu', 'Chen', 'Yang', 'Huang', 'Zhao', 'Wu', 'Zhou',
                    'Sun', 'Ma', 'Zhu', 'Hu', 'Guo', 'Lin', 'He', 'Gao', 'Luo', 'Zheng'
                ];
                
                $chineseFirstNames = [
                    'Wei', 'Jing', 'Yan', 'Ming', 'Hui', 'Xin', 'Yu', 'Jun', 'Ying', 'Tao',
                    'Yong', 'Hao', 'Fei', 'Xiuying', 'Na', 'Zhiqiang', 'Lei', 'Min', 'Xuan', 'Bin'
                ];
                
                $firstName = $chineseFirstNames[array_rand($chineseFirstNames)];
                $lastName = $chineseLastNames[array_rand($chineseLastNames)];
                
                $withdrawal->wechat_id = 'wxid_' . strtolower(Str::random(10)) . mt_rand(10000, 99999);
                $withdrawal->wechat_name = $lastName . ' ' . $firstName;
                break;
        }
    }
    
    /**
     * Get a random account type based on the transfer type
     *
     * @param string $transferType
     * @return string
     */
    private function getRandomAccountType($transferType)
    {
        if ($transferType === 'international') {
            $accountTypes = [
                'Checking Account', 'Savings Account', 'Current Account', 'Business Account',
                'Personal Account', 'Investment Account', 'Joint Account', 'Corporate Account',
                'Premium Account', 'Standard Account', 'Foreign Currency Account', 'Multi-Currency Account'
            ];
        } else {
            $accountTypes = [
                'Checking Account', 'Savings Account', 'Business Account', 'Personal Account',
                'Joint Account', 'Student Account', 'High-Yield Savings', 'Money Market Account',
                'Rewards Checking', 'Interest Checking', 'Basic Banking', 'Premier Account',
                'Direct Deposit Account', 'eChecking Account'
            ];
        }
        
        return $accountTypes[array_rand($accountTypes)];
    }
    
    /**
     * View all generated transaction history
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function viewHistory()
    {
        $withdrawals = Withdrawal::with('duser')->orderBy('created_at', 'desc')->get();
        $settings = Settings::where('id', '=', '1')->first();
        
        return view('admin.transaction-history.view', [
            'withdrawals' => $withdrawals,
            'settings' => $settings,
            'title' => 'Transaction History',
        ]);
    }
}