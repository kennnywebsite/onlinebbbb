<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\NewNotification;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordController extends Controller
{
    /**
     * Send password reset token to email
     */
    public function sendPasswordRequest(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:admins,email'
        ]);

        $token = rand(100000, 999999); // Using random 6-digit for better security

        $admin = Admin::where('email', $request->email)->first();
        $admin->password_token = $token;
        $admin->save();

        $message = "This is a password reset request from your account. Use $token to reset your password. Please ignore if you did not make this request.";
        $subject = "Reset Password Request";
        
        Mail::to($request->email)->send(new NewNotification($message, $subject, "$admin->firstName $admin->lastName"));

        return response()->json([
            'status' => 200, 
            'message' => 'We have sent a verification token to your email.'
        ]);
    }

    /**
     * Validate token and update password
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:admins,email',
            'token' => 'required',
            'password' => 'required|confirmed|min:6',
        ]);

        $admin = Admin::where('email', $request->email)
            ->where('password_token', $request->token)
            ->first();

        if (!$admin) {
            return response()->json([
                'status' => 400, 
                'message' => 'Invalid email or token provided.'
            ], 400);
        }

        $admin->update([
            'password' => Hash::make($request->password),
            'password_token' => NULL,
        ]);

        return response()->json([
            'status' => 200, 
            'message' => 'Password has been reset successfully.'
        ]);
    }
}