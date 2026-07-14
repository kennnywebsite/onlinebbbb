<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;

class EmailVerificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }
    
    /**
     * Check if email is verified
     */
    public function status(Request $request)
    {
        return response()->json([
            'verified' => $request->user()->hasVerifiedEmail()
        ]);
    }
    
    /**
     * Verify the email with the code.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'verification_code' => 'required|string',
        ]);
        
        $user = $request->user();
        
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 400);
        }
        
        if ($user->verifyEmailWithCode($request->verification_code)) {
            event(new Verified($user));
            
            return response()->json([
                'status'  => 200,
                'message' => 'Your email has been verified successfully.'
            ]);
        }
        
        return response()->json([
            'status'  => 422,
            'message' => 'The verification code is invalid or has expired.'
        ], 422);
    }
    
    /**
     * Resend the email verification notification.
     */
    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 400);
        }
        
        $request->user()->sendEmailVerificationNotification();
        
        return response()->json([
            'status'  => 200,
            'message' => 'Verification code sent successfully!'
        ]);
    }
}