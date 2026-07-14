<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\CardSettings;
use App\Helpers\NotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CardController extends Controller
{
    /**
     * Dashboard: Get card overview and list
     */
    public function index()
    {
        $user = Auth::user();
        $cards = $user->cards()->whereIn('status', ['active', 'pending', 'inactive', 'blocked'])->get();

        return response()->json([
            'status' => 200,
            'data' => [
                'cards' => $cards,
                'stats' => [
                    'active'   => $cards->where('status', 'active')->count(),
                    'pending'  => $cards->where('status', 'pending')->count(),
                    'inactive' => $cards->where('status', 'inactive')->count(),
                    'blocked'  => $cards->where('status', 'blocked')->count(),
                    'total_balance' => $cards->where('status', 'active')->sum('balance'),
                ]
            ]
        ]);
    }

    /**
     * Application Settings (Metadata)
     */
    public function getApplicationMetadata()
    {
        $settings = CardSettings::first();
        if (!$settings || !$settings->is_enabled) {
            return response()->json(['message' => 'Virtual cards unavailable.'], 404);
        }

        return response()->json([
            'issuance_fees' => [
                'standard' => $settings->standard_fee,
                'gold'     => $settings->gold_fee,
                'platinum' => $settings->platinum_fee,
                'black'    => $settings->black_fee,
            ],
            'limits' => [
                'min' => $settings->min_daily_limit,
                'max' => $settings->max_daily_limit
            ]
        ]);
    }

    /**
     * Process Application
     */
    public function applyCard(Request $request)
    {
        $settings = CardSettings::first();
        if (!$settings || !$settings->is_enabled) return response()->json(['message' => 'Unavailable'], 400);

        $request->validate([
            'card_type' => 'required|in:visa,mastercard,american_express,discover',
            'card_level' => 'required|in:standard,gold,platinum,black',
            'daily_limit' => 'nullable|numeric',
        ]);

        $user = Auth::user();
        if ($user->account_status !== 'active') return response()->json(['message' => 'Account dormant'], 403);

        $fee = $settings->{$request->card_level . '_fee'};
        if ($user->account_bal < $fee) return response()->json(['message' => 'Insufficient funds'], 400);

        $card = Card::create([
            'user_id' => $user->id,
            'card_holder_name' => $request->card_holder_name,
            'card_type' => $request->card_type,
            'card_level' => $request->card_level,
            'daily_limit' => $request->daily_limit ?? $settings->min_daily_limit,
            'status' => 'pending',
            'reference_id' => 'CARD' . strtoupper(Str::random(10)),
        ]);

        $user->decrement('account_bal', $fee);

        return response()->json(['status' => 200, 'message' => 'Application submitted', 'card_id' => $card->id]);
    }

    /**
     * Card Actions (Activate/Deactivate/Block)
     */
    public function updateStatus(Request $request, $id)
    {
        $card = Card::where('user_id', Auth::id())->findOrFail($id);
        $status = $request->status; // 'active', 'inactive', 'blocked'

        if ($status == 'active' && $card->status !== 'inactive') {
            return response()->json(['message' => 'Cannot activate'], 400);
        }

        $card->update(['status' => $status]);

        NotificationHelper::create(
            Auth::user(),
            "Card status changed to {$status}",
            'Card Update',
            'info',
            'credit-card',
            "/cards/{$id}"
        );

        return response()->json(['status' => 200, 'message' => 'Card updated']);
    }

    /**
     * Get specific card transactions
     */
    public function getTransactions($id)
    {
        $card = Card::where('user_id', Auth::id())->findOrFail($id);
        return response()->json([
            'status' => 200,
            'data' => $card->transactions()->latest()->paginate(15)
        ]);
    }
}