<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Settings;
use App\Mail\NewNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TwoFactorController extends Controller
{
    /**
     * Verify the 2FA code for Admin API
     */
    public function verifyTwoFactor(Request $request)
    {
        $request->validate([
            'twofa' => 'required|string',
        ]);

        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Verify code and expiry
        if ($request->twofa == $admin->token_2fa && Carbon::now()->lt($admin->token_2fa_expiry)) {

            $admin->update([
                'token_2fa_expiry' => Carbon::now()->addMinutes(config('session.lifetime')),
                'pass_2fa' => 'true',
            ]);

            // Notify Admin
            $message = "This is a successful login notification on your admin account. If this was not you, change your password immediately.";
            Mail::bcc($admin->email)->send(new NewNotification($message, "Successful login", $admin->email));

            // Generate an access token for the admin session
            $token = $admin->createToken('admin_token')->plainTextToken;

            return response()->json([
                'status' => 200,
                'message' => '2FA verification successful.',
                'access_token' => $token,
                'token_type' => 'Bearer'
            ]);
        }

        return response()->json(['message' => 'Incorrect or expired code.'], 422);
    }
}