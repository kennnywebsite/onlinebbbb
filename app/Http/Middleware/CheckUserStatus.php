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

        // 1. Skip middleware if the request is for the admin area
        // This prevents the web-based status logic from interfering with Admin sessions
        if ($request->is('admin/*') || $request->is('auth/*')) {
            Log::info('CheckUserStatus: Admin area detected, skipping status check.');
            return $next($request);
        }

        // 2. Check Web user status
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