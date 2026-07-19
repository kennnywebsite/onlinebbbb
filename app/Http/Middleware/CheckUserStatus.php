<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. Always allow access to login pages to avoid redirect loops
        if ($request->is('login') || $request->is('admin/auth/login')) {
            return $next($request);
        }

        // 2. If an Admin is logged in, skip the 'web' status check entirely.
        // This prevents the middleware from accidentally logging out an Admin 
        // who is trying to access their dashboard.
        if (Auth::guard('admin')->check()) {
            return $next($request);
        }

        // 3. Only perform the status check if a regular user is logged in
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            
            if ($user->status !== 'active') {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken(); // Good practice to regenerate
                
                return redirect()->route('login')->with('message', 'Your account is inactive.');
            }
        }

        return $next($request);
    }
}