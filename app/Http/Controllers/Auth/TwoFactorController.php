<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\TwoFactorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorController extends Controller
{
    /**
     * Generate and send a new two-factor authentication code.
     */
    public function generateTwoFactorCode()
    {
        $user = Auth::user();
        
        $code = mt_rand(100000, 999999);
        
        $user->update([
            'two_factor_code' => $code,
            'two_factor_expires_at' => now()->addMinutes(10)
        ]);
        
        $user->notify(new TwoFactorCode($code));
        
        return response()->json(['message' => 'Verification code sent to your email.']);
    }

    /**
     * Verify the two-factor authentication code.
     */
    public function verifyTwoFactorCode(Request $request)
    {
        $request->validate([
            'two_factor_code' => 'required|string|size:6',
        ]);

        $user = Auth::user();
        
        // Check if code matches and is not expired
        if ($user->two_factor_code == $request->two_factor_code && 
            $user->two_factor_expires_at && 
            now()->lt($user->two_factor_expires_at)) {
            
            // Reset the code
            $user->update([
                'two_factor_code' => null,
                'two_factor_expires_at' => null
            ]);
            
            // In API, return a success token or status to unlock the dashboard
            return response()->json(['status' => 200, 'message' => '2FA Verified successfully.']);
        }
        
        return response()->json(['message' => 'Invalid or expired verification code.'], 422);
    }

    /**
     * Resend the code (with throttle check)
     */
    public function resendTwoFactorCode()
    {
        $user = Auth::user();
        
        // Prevent abuse: check if code is still "fresh" (e.g., requested within the last minute)
        if ($user->two_factor_expires_at && now()->lt($user->two_factor_expires_at->addMinutes(9))) {
            return response()->json(['message' => 'Please wait before requesting a new code.'], 429);
        }
        
        return $this->generateTwoFactorCode();
    }
    
    /**
     * Toggle 2FA status
     */
    public function toggleTwoFactor()
    {
        $user = Auth::user();
        $user->two_factor_enabled = !$user->two_factor_enabled;
        $user->save();
        
        return response()->json([
            'message' => "Two-factor authentication has been " . ($user->two_factor_enabled ? 'enabled' : 'disabled'),
            'enabled' => (bool)$user->two_factor_enabled
        ]);
    }
}