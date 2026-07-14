<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CryptoAccount;
use App\Models\CryptoRecord;
use App\Models\SettingsCont;
use App\Models\User;
use App\Traits\Apitrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExchangeController extends Controller
{
    use Apitrait;

    public function getprice(Request $request, $base, $quote, $amount)
    {
        $settings = SettingsCont::find(1);
        $pluscharge = $amount * ($settings->fee / 100);
        $amount_to = $amount - $pluscharge;

        $prices = 0;

        if ($base === $quote) {
            $prices = $amount_to;
        } elseif ($base === "usd") {
            $rate = $this->get_rate($quote, 'usd', 'price');
            $prices = $amount_to / $rate;
        } elseif ($quote === "usd") {
            $rate = $this->get_rate($base, 'usd', 'price');
            $prices = $amount_to * $rate;
        } else {
            $rate1 = $this->get_rate($base, 'usd', 'price');
            $rate2 = $this->get_rate($quote, 'usd', 'price');
            $prices = $amount_to * ($rate1 / $rate2);
        }

        return response()->json(['status' => 200, 'data' => round($prices, 8)]);
    }

    public function exchange(Request $request)
    {
        $request->validate([
            'source' => 'required',
            'destination' => 'required',
            'amount' => 'required|numeric|min:0.00000001',
            'quantity' => 'required|numeric'
        ]);

        $user = Auth::user();
        $crypto = CryptoAccount::where('user_id', $user->id)->firstOrFail();
        $src = $request->source;
        $dest = $request->destination;

        DB::beginTransaction();
        try {
            // 1. Deduct Source
            if ($src === 'usd') {
                if ($user->account_bal < $request->amount) throw new \Exception('Insufficient funds');
                $user->decrement('account_bal', $request->amount);
            } else {
                if (($crypto->$src ?? 0) < $request->amount) throw new \Exception('Insufficient crypto balance');
                $crypto->decrement($src, $request->amount);
            }

            // 2. Add Destination
            if ($dest === 'usd') {
                $user->increment('account_bal', $request->quantity);
            } else {
                $crypto->increment($dest, $request->quantity);
            }

            // 3. Log Record
            CryptoRecord::create([
                'source'   => strtoupper($src),
                'dest'     => strtoupper($dest),
                'amount'   => $request->amount,
                'quantity' => $request->quantity,
            ]);

            DB::commit();
            return response()->json(['status' => 200, 'message' => 'Exchange Successful']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        }
    }

    public function getBalance($coin)
    {
        $user = Auth::user();
        $crypto = CryptoAccount::where('user_id', $user->id)->first();
        $amount = $crypto->$coin ?? 0;

        $dollar = $this->get_rate($coin, 'usd');
        $mainbal = $amount * $dollar;

        // Simplified currency formatting
        return response()->json(['status' => 200, 'data' => number_format($mainbal, 2)]);
    }
}