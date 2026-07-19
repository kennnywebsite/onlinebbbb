<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. Skip status checks for login/auth routes to prevent infinite loops 
        // or interference with the admin authentication process.
        if ($request->is('login') || $request->is('admin/*') || $request->is('auth/login')) {
            return $next($request);
        }

        // 2. Only check status if the 'web' (User) guard is active
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            
            // If the user status is not active, log them out and redirect
            if ($user->status !== 'active') {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                
                return redirect()->route('login')->with('message', 'Your account is inactive.');
            }
        }

        return $next($request);
    }
}