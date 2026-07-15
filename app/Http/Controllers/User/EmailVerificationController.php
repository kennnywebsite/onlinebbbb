<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;

class EmailVerificationController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }
    
    /**
     * Display the email verification notice.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->route('dashboard')
            : view('auth.verify-email');
    }
    
    /**
     * Verify the email with the given code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verify(Request $request)
    {
        $request->validate([
            'verification_code' => 'required|string',
        ]);
        
        $user = $request->user();
        
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }
        
        if ($user->verifyEmailWithCode($request->verification_code)) {
            event(new Verified($user));
            
            return redirect()->route('dashboard')->with('verified', true)
                ->with('success', 'Your email has been verified successfully.');
        }
        
        return back()->with('message', 'The verification code is invalid or has expired.');
    }
    
    /**
     * Resend the email verification notification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }
        
        $request->user()->sendEmailVerificationNotification();
        
        return back()->with('status', 'Verification code sent!');
    }
} 