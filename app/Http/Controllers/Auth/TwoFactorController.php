<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\TwoFactorCode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class TwoFactorController extends Controller
{
    /**
     * Show the two-factor authentication challenge view.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('auth.two-factor-challenge');
    }

    /**
     * Generate and send a new two-factor authentication code.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function generateTwoFactorCode()
    {
        $user = Auth::user();
        
        // Generate a random verification code
        $code = mt_rand(100000, 999999);
        
        // Store the verification code and expiry in database
        $user->two_factor_code = $code;
        $user->two_factor_expires_at = now()->addMinutes(10);
        $user->save();
        
        // Send the notification with the code
        $user->notify(new TwoFactorCode($code));
        
        return redirect()->route('two-factor.challenge')
            ->with('success', 'A new verification code has been sent to your email.');
    }

    /**
     * Verify the two-factor authentication code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
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
            $user->two_factor_code = null;
            $user->two_factor_expires_at = null;
            $user->save();
            
            // Set a session variable to indicate 2FA is completed for this session
            Session::put('two_factor_authenticated', true);
            
            return redirect()->intended(route('dashboard'));
        }
        
        return redirect()->route('two-factor.challenge')
            ->with('message', 'The verification code you entered is invalid or has expired.');
    }

    /**
     * Resend the two-factor authentication code.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resendTwoFactorCode()
    {
        $user = Auth::user();
        
        // Check if we can send a new code (prevent abuse)
        if ($user->two_factor_expires_at && now()->lt($user->two_factor_expires_at->subMinutes(9))) {
            return redirect()->route('two-factor.challenge')
                ->with('message', 'Please wait before requesting a new code.');
        }
        
        return $this->generateTwoFactorCode();
    }
    
    /**
     * Toggle the two-factor authentication status for the authenticated user.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggleTwoFactor()
    {
        $user = Auth::user();
        $user->two_factor_enabled = !$user->two_factor_enabled;
        $user->save();
        
        $status = $user->two_factor_enabled ? 'enabled' : 'disabled';
        
        return redirect()->back()
            ->with('success', "Two-factor authentication has been {$status}.");
    }
}
