<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Settings;

class ReferralSettingsController extends Controller
{
    /**
     * Get Referral and Bonus Settings
     */
    public function index()
    {
        $settings = Settings::first();
        
        return response()->json([
            'status' => 200,
            'data' => [
                'referral_commission'  => $settings->referral_commission,
                'referral_commission1' => $settings->referral_commission1,
                'referral_commission2' => $settings->referral_commission2,
                'referral_commission3' => $settings->referral_commission3,
                'referral_commission4' => $settings->referral_commission4,
                'referral_commission5' => $settings->referral_commission5,
                'signup_bonus'         => $settings->signup_bonus,
                'deposit_bonus'        => $settings->deposit_bonus,
            ]
        ]);
    }

    /**
     * Update Referral Commissions
     */
    public function updateRefBonus(Request $request)
    {
        $request->validate([
            'ref_commission'  => 'required|numeric',
            'ref_commission1' => 'required|numeric',
            'ref_commission2' => 'required|numeric',
            'ref_commission3' => 'required|numeric',
            'ref_commission4' => 'required|numeric',
            'ref_commission5' => 'required|numeric',
            'signup_bonus'    => 'required|numeric',
        ]);

        Settings::where('id', $request->id)->update([
            'referral_commission'  => $request->ref_commission,
            'referral_commission1' => $request->ref_commission1,
            'referral_commission2' => $request->ref_commission2,
            'referral_commission3' => $request->ref_commission3,
            'referral_commission4' => $request->ref_commission4,
            'referral_commission5' => $request->ref_commission5,
            'signup_bonus'         => $request->signup_bonus,
        ]);

        return response()->json(['status' => 200, 'message' => 'Referral Bonus Settings Saved successfully']);
    }

    /**
     * Update Extra Bonus Settings
     */
    public function updateExtraBonus(Request $request)
    {
        $request->validate([
            'deposit_bonus' => 'required|numeric',
            'signup_bonus'  => 'required|numeric',
        ]);

        Settings::where('id', $request->id)->update([
            'deposit_bonus' => $request->deposit_bonus,
            'signup_bonus'  => $request->signup_bonus,
        ]);

        return response()->json(['status' => 200, 'message' => 'Extra Bonus Settings Saved successfully']);
    }
}