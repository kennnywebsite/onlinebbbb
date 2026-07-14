<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an API login request.
     */
    public function store(Request $request)
    {
        // 1. Validate the request
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string', // Optional: helps identify the device
        ]);

        // 2. Attempt authentication
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $user = Auth::user();

        // 3. Create API Token (Sanctum)
        $tokenName = $request->device_name ?? 'mobile_app';
        $token = $user->createToken($tokenName)->plainTextToken;

        // 4. Return JSON response
        return response()->json([
            'status' => 200,
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    /**
     * Handle logout (Revoke token)
     */
    public function destroy(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Logged out successfully'
        ]);
    }
}