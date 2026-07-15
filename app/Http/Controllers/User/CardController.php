<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\CardSettings;
use App\Helpers\NotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CardController extends Controller
{
    /**
     * Display the virtual cards dashboard.
     *
     * @return \Illuminate\Http\Response
     */
   public function index()
{
    $user = Auth::user();

    // Fetch all cards grouped by status
    $cards = $user->cards()
        ->whereIn('status', ['active', 'pending', 'inactive', 'blocked'])
        ->get()
        ->groupBy('status');

    $activeCards = $cards->get('active', collect());
    $pendingCards = $cards->get('pending', collect());
    $inactiveCards = $cards->get('inactive', collect());
    $blockedCards = $cards->get('blocked', collect());

    return view('user.cards.index', [
        'title' => 'Virtual Cards',
        'cards' => $cards->flatten(), // All cards
        'activeCards' => $activeCards->count(),
        'pendingCards' => $pendingCards->count(),
        'inactiveCards' => $inactiveCards->count(),
        'blockedCards' => $blockedCards->count(),
        'totalBalance' => $activeCards->sum('balance'),
    ]);
}


    /**
     * Show the application form for a new card.
     *
     * @return \Illuminate\Http\Response
     */
    public function showApplicationForm()
    {
        $cardSettings = CardSettings::first();
        
        // If card settings don't exist or virtual cards are disabled, redirect back with message
        if (!$cardSettings || !$cardSettings->is_enabled) {
            return redirect()->route('cards')->with('message', 'Virtual cards are currently unavailable. Please try again later.')
                ->with('type', 'error');
        }
        
        $issuanceFees = [
            'standard' => $cardSettings->standard_fee,
            'gold' => $cardSettings->gold_fee,
            'platinum' => $cardSettings->platinum_fee,
            'black' => $cardSettings->black_fee,
        ];
        
        return view('user.cards.apply', [
            'title' => 'Apply for Virtual Card',
            'issuanceFees' => $issuanceFees,
            'minLimit' => $cardSettings->min_daily_limit,
            'maxLimit' => $cardSettings->max_daily_limit,
        ]);
    }

    /**
     * Process a new card application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function applyCard(Request $request)
    {
        $cardSettings = CardSettings::first();
        
        // If card settings don't exist or virtual cards are disabled, redirect back with message
        if (!$cardSettings || !$cardSettings->is_enabled) {
            return redirect()->route('cards')->with('message', 'Virtual cards are currently unavailable. Please try again later.')
                ->with('type', 'error');
        }
        
        $request->validate([
            'card_type' => 'required|string|in:visa,mastercard,american_express,discover',
            'card_level' => 'required|string|in:standard,gold,platinum,black',
            'daily_limit' => 'nullable|numeric|min:' . $cardSettings->min_daily_limit . '|max:' . $cardSettings->max_daily_limit,
            'currency' => 'required|string|max:10',
            'card_holder_name' => 'required|string|max:255',
            'billing_address' => 'required|string|max:255',
            'billing_city' => 'required|string|max:100',
            'billing_zip' => 'required|string|max:20',
            'terms' => 'required|accepted',
        ]);

        $user = Auth::user();
        
        if($user->account_status != 'active'){
            $settings = \App\Models\Settings::first();
            return redirect()->back()
                ->with("message", "Sorry, your account is dormant. Contact support on " . ($settings->contact_email ?? 'support@example.com') . " for more details.")
                ->with('type', 'error');
        }
        
        // Get appropriate issuance fee based on card level
        $feeKey = $request->card_level . '_fee';
        $issuanceFee = $cardSettings->$feeKey;
        
        // Check if user has sufficient balance
        if ($user->account_bal < $issuanceFee) {
            return redirect()->back()->with('message', 'Insufficient account balance to cover card issuance fee of $' . number_format($issuanceFee, 2) . '.')
                ->with('type', 'error');
        }

        // Create the card with pending status
        $card = new Card();
        $card->user_id = $user->id;
        $card->card_holder_name = $request->card_holder_name;
        $card->card_type = $request->card_type;
        $card->daily_limit = $request->daily_limit ?? $cardSettings->min_daily_limit;
        $card->card_level = $request->card_level;
        $card->currency = $request->currency;
        $card->status = 'pending';
        $card->billing_address = $request->billing_address;
        $card->billing_city = $request->billing_city;
        $card->billing_zip = $request->billing_zip;
        $card->application_date = now();
        $card->reference_id = 'CARD' . strtoupper(Str::random(10));
        $card->is_virtual = true;
        $card->save();

        // Charge the issuance fee
        $user->account_bal -= $issuanceFee;
        $user->save();
        
        // Create fee transaction
        CardTransaction::create([
            'card_id' => $card->id,
            'user_id' => $user->id,
            'amount' => $issuanceFee,
            'currency' => $request->currency,
            'transaction_type' => 'fee',
            'transaction_reference' => 'FEE' . strtoupper(Str::random(8)),
            'merchant_name' => config('app.name'),
            'status' => 'completed',
            'description' => 'Card issuance fee for ' . ucfirst($request->card_level) . ' card',
            'transaction_date' => now(),
        ]);

        // Create notification
        NotificationHelper::create(
            $user,
            'Your card application has been submitted and is awaiting approval. You will be notified when the status changes.',
            'Card Application Submitted',
            'info',
            'credit-card',
            route('cards.view', $card->id)
        );

        return redirect()->route('cards')->with('success', 'Your virtual card application has been submitted successfully. It is now pending approval.')
            ->with('type', 'success');
    }

    /**
     * Display a specific card's details.
     *
     * @param  \App\Models\Card  $card
     * @return \Illuminate\Http\Response
     */
    public function viewCard(Card $card)
    {
       $user = Auth::user();

        // Get recent transactions for this card
        $transactions = $card->transactions()
            ->latest('transaction_date')
            ->take(10)
            ->get();

        return view('user.cards.view', [
            'title' => 'Card Details',
            'card' => $card,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Activate a card.
     *
     * @param  \App\Models\Card  $card
     * @return \Illuminate\Http\Response
     */
    public function activateCard(Card $card)
    {
        $user = Auth::user();

        if ($card->status !== 'inactive') {
            return back()->with('success', 'This card cannot be activated.')
                ->with('type', 'danger');
        }

        $card->status = 'active';
        $card->save();

        // Create notification
        NotificationHelper::create(
            Auth::user(),
            'Your ' . ucfirst($card->card_level) . ' ' . ucfirst(str_replace('_', ' ', $card->card_type)) . ' card ending in ' . $card->last_four . ' has been activated successfully.',
            'Card Activated',
            'success',
            'check-circle',
            route('cards.view', $card->id)
        );

        return back()->with('success', 'Card has been activated successfully.')
            ->with('type', 'success');
    }

    /**
     * Deactivate a card.
     *
     * @param  \App\Models\Card  $card
     * @return \Illuminate\Http\Response
     */
    public function deactivateCard(Card $card)
    {
        $user = Auth::user();

        if ($card->status !== 'active') {
            return back()->with('message', 'This card cannot be deactivated.')
                ->with('type', 'danger');
        }

        $card->status = 'inactive';
        $card->save();

        // Create notification
        NotificationHelper::create(
            Auth::user(),
            'Your ' . ucfirst($card->card_level) . ' ' . ucfirst(str_replace('_', ' ', $card->card_type)) . ' card ending in ' . $card->last_four . ' has been deactivated.',
            'Card Deactivated',
            'warning',
            'pause',
            route('cards.view', $card->id)
        );

        return back()->with('success', 'Card has been deactivated successfully.')
            ->with('type', 'success');
    }

    /**
     * Block a card.
     *
     * @param  \App\Models\Card  $card
     * @return \Illuminate\Http\Response
     */
    public function blockCard(Card $card)
    {
       $user = Auth::user();

        if (!in_array($card->status, ['active', 'inactive'])) {
            return back()->with('message', 'This card cannot be blocked.')
                ->with('type', 'danger');
        }

        $card->status = 'blocked';
        $card->save();

        // Create notification
        NotificationHelper::create(
            Auth::user(),
            'Your ' . ucfirst($card->card_level) . ' ' . ucfirst(str_replace('_', ' ', $card->card_type)) . ' card ending in ' . $card->last_four . ' has been blocked for security reasons. Please contact support if you didn\'t request this action.',
            'Card Blocked',
            'danger',
            'lock',
            route('cards.view', $card->id)
        );

        return back()->with('message', 'Card has been blocked. Please contact support for assistance.')
            ->with('type', 'success');
    }

    /**
     * Display card transactions.
     *
     * @param  \App\Models\Card  $card
     * @return \Illuminate\Http\Response
     */
    public function cardTransactions(Card $card)
    {
        $user = Auth::user();

        $transactions = $card->transactions()
            ->latest('transaction_date')
            ->paginate(15);

        return view('user.cards.transactions', [
            'title' => 'Card Transactions',
            'card' => $card,
            'transactions' => $transactions,
        ]);
    }
} 