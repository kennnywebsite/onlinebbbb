<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Settings;
use App\Models\CryptoAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Hash;
use App\Mail\NewRegistration;
use Illuminate\Support\Facades\Mail;

class SocialLoginController extends Controller
{
    /**
     * Mobile/SPA flow: Client sends the social token/code to this endpoint
     */
    public function authenticate(Request $request, $social)
    {
        // 1. Get user info from provider using the token provided by the frontend
        try {
            $userSocial = Socialite::driver($social)->stateless()->user();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = User::where('email', $userSocial->getEmail())->first();
        $settings = Settings::first();

        if ($user) {
            // Existing user login
            $token = $user->createToken('auth_token')->plainTextToken;
            return $this->respondWithToken($token, $user);
        } else {
            // New User registration
            $password = $this->RandomStringGenerator(8);
            
            $newUser = User::create([
                'name' => $userSocial->getName(),
                'email' => $userSocial->getEmail(),
                'email_verified_at' => now(),
                'status' => 'active',
                'username' => str_replace(' ', '', $userSocial->getName()) . time(),
                'password' => Hash::make($password),
            ]);

            CryptoAccount::create(['user_id' => $newUser->id]);

            // Send welcome email
            $objDemo = (object)[
                'password' => $password,
                'sender' => $settings->site_name,
                'contact_email' => $settings->contact_email,
            ];
            Mail::to($newUser->email)->send(new NewRegistration($objDemo));

            $token = $newUser->createToken('auth_token')->plainTextToken;
            return $this->respondWithToken($token, $newUser, "Account created successfully.");
        }
    }

    private function respondWithToken($token, $user, $message = "Login successful")
    {
        return response()->json([
            'status' => 200,
            'message' => $message,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    private function RandomStringGenerator($n)
    {
        $domain = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
        return substr(str_shuffle(str_repeat($domain, $n)), 0, $n);
    }
}