<?php

namespace App\Http\Controllers\Api;

use App\Actions\Fortify\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Models\CryptoAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

class ApiAuthController extends Controller
{
    use PasswordValidationRules;

    public function register(Request $request)
    {
        // 1. Validation with unique checks
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'username' => 'required|string|max:255|unique:users',
            'phone'    => 'required|string|max:20',
            'country'  => 'required|string|max:100',
            'password' => ['required', 'confirmed', Password::defaults()],
            'ref_by'   => 'nullable|string' // Capture referral username
        ]);

        // 2. Handle Referral ID lookup
        $ref_by_id = null;
        if ($request->filled('ref_by')) {
            $referrer = User::where('username', $request->ref_by)->first();
            if ($referrer) {
                $ref_by_id = $referrer->id;
            }
        }

        // 3. Create User
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'username' => $request->username,
            'country'  => $request->country,
            'ref_by'   => $ref_by_id,
            'status'   => 'active',
            'password' => Hash::make($request->password),
        ]);

        // 4. Create Crypto Account
        CryptoAccount::create(['user_id' => $user->id]);

        // 5. Send Email
        try {
            Mail::to($user->email)->send(new WelcomeEmail($user));
        } catch (\Exception $e) {
            // Log email failure but don't stop registration
        }

        // 6. Generate Token for instant login (Sanctum)
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status'  => 200,
            'message' => 'Registration successful.',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'    => $user
        ], 201);
    }
}