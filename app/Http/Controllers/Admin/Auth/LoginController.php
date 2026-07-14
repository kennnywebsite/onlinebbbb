<?php

namespace App\Http\Controllers\Api\Admin\Auth;

use App\Models\Admin;
use App\Models\Settings;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Mail\Twofa;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class LoginController extends Controller
{
    /**
     * Handle Admin Login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|exists:admins,email',
            'password' => 'required|string',
        ]);

        $admin = Admin::where('email', $request->email)->first();

        // Verify password and status
        if (!$admin || !Hash::check($request->password, $admin->password) || $admin->status !== 'active') {
            return response()->json(['status' => 401, 'message' => 'Invalid credentials or account inactive.'], 401);
        }

        // Handle 2FA
        if ($admin->enable_2fa == "enabled") {
            $token = mt_rand(10000, 99999);
            $admin->update([
                'token_2fa' => $token,
                'pass_2fa' => 'false',
            ]);

            $settings = Settings::first();
            $objDemo = new \stdClass();
            $objDemo->message = $token;
            $objDemo->sender = $settings->site_name;
            $objDemo->subject = "Two Factor Code";
            
            Mail::to($admin->email)->send(new Twofa($objDemo));

            return response()->json([
                'status' => 202, // Accepted for 2FA
                'message' => '2FA code sent to your email.'
            ]);
        }

        // Generate Sanctum Token
        $authToken = $admin->createToken('admin_token')->plainTextToken;

        return response()->json([
            'status' => 200,
            'message' => 'Login successful',
            'token' => $authToken,
            'admin' => $admin
        ]);
    }

    /**
     * Logout Admin
     */
    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Admin logged out successfully.'
        ]);
    }
}