<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckUserStatus
{
    public function handle(Request $request, Closure $next)
    {
        Log::info('CheckUserStatus: Checking path: ' . $request->path());

        // Skip middleware if admin
        if (Auth::guard('admin')->check()) {
            Log::info('CheckUserStatus: Admin detected, skipping status check.');
            return $next($request);
        }

        // Check Web user status
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            Log::info('CheckUserStatus: Checking web user status for ID: ' . $user->id);
            
            if ($user->status !== 'active') {
                Log::warning('CheckUserStatus: User inactive. Logging out.');
                Auth::guard('web')->logout();
                return redirect()->route('login');
            }
        }

        return $next($request);
    }
}