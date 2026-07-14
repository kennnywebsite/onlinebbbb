<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Card, CardTransaction, User, CardSettings};
use App\Helpers\NotificationHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VirtualCardController extends Controller
{
    // List all cards
    public function index()
    {
        return response()->json([
            'status' => 200,
            'data' => Card::with('user')->latest()->paginate(20)
        ]);
    }

    // View specific card details
    public function show($id)
    {
        $card = Card::with('user')->findOrFail($id);
        return response()->json([
            'status' => 200,
            'data' => [
                'card' => $card,
                'transactions' => CardTransaction::where('card_id', $id)->latest()->paginate(10)
            ]
        ]);
    }

    // Approve Card Application
    public function approve($id)
    {
        $card = Card::findOrFail($id);
        $user = User::findOrFail($card->user_id);
        
        $details = $this->generateCardDetails($card->card_type);
        
        $card->update(array_merge($details, ['status' => 'active']));
        
        // Notification
        NotificationHelper::create($user, "Your card has been approved.", "Card Approved", "success", "check-circle", "");
        
        return response()->json(['status' => 200, 'message' => 'Card approved successfully.']);
    }

    // Change status (Reject/Block/Unblock)
    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:rejected,blocked,active']);
        $card = Card::findOrFail($id);
        $card->update(['status' => $request->status]);

        return response()->json(['status' => 200, 'message' => "Card status set to {$request->status}"]);
    }

    // Top up Card
    public function topup(Request $request, $id)
    {
        $request->validate(['amount' => 'required|numeric|min:1']);
        
        DB::transaction(function () use ($request, $id) {
            $card = Card::findOrFail($id);
            $card->increment('balance', $request->amount);
            
            CardTransaction::create([
                'card_id' => $card->id,
                'user_id' => $card->user_id,
                'amount' => $request->amount,
                'transaction_type' => 'topup',
                'status' => 'completed',
                'transaction_reference' => 'TOP' . Str::random(10),
            ]);
        });

        return response()->json(['status' => 200, 'message' => 'Card topped up successfully.']);
    }

    // Deduct from Card
    public function deduct(Request $request, $id)
    {
        $request->validate(['amount' => 'required|numeric|min:1']);
        
        $card = Card::findOrFail($id);
        if ($card->balance < $request->amount) {
            return response()->json(['status' => 400, 'message' => 'Insufficient card balance.'], 400);
        }
        
        $card->decrement('balance', $request->amount);
        
        CardTransaction::create([
            'card_id' => $card->id,
            'user_id' => $card->user_id,
            'amount' => -$request->amount,
            'transaction_type' => 'deduction',
            'status' => 'completed',
            'transaction_reference' => 'DED' . Str::random(10),
        ]);

        return response()->json(['status' => 200, 'message' => 'Deduction successful.']);
    }

    // --- HELPER METHODS ---

    private function generateCardDetails($cardType)
    {
        $type = strtolower($cardType);
        $expiryMonth = str_pad(date('n'), 2, '0', STR_PAD_LEFT);
        $expiryYear = date('Y') + 3;
        $cvv = str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
        
        // Logic for Card Number
        $bin = (strpos($type, 'visa') !== false) ? '4' : '5';
        $cardNumber = $bin . $this->generateRandomDigits(14);
        $cardNumber = $this->applyLuhnAlgorithm($cardNumber);
        
        return [
            'card_number' => $cardNumber,
            'expiry_month' => $expiryMonth,
            'expiry_year' => $expiryYear,
            'cvv' => $cvv,
            'last_four' => substr($cardNumber, -4),
            'bin' => substr($cardNumber, 0, 6),
            'card_pan' => encrypt($cardNumber), // Use Laravel built-in encryption
            'card_token' => Str::random(32),
        ];
    }

    private function generateRandomDigits($length)
    {
        $digits = '';
        for ($i = 0; $i < $length; $i++) { $digits .= mt_rand(0, 9); }
        return $digits;
    }

    private function applyLuhnAlgorithm($cardNumber)
    {
        $number = substr($cardNumber, 0, -1);
        $sum = 0;
        $length = strlen($number);
        for ($i = $length - 1; $i >= 0; $i--) {
            $digit = (int)$number[$i];
            if (($length - $i) % 2 === 0) {
                $digit *= 2;
                if ($digit > 9) $digit -= 9;
            }
            $sum += $digit;
        }
        $checkDigit = (10 - ($sum % 10)) % 10;
        return $number . $checkDigit;
    }
}