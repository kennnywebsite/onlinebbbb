<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserStatus
{
    public function handle(Request $request, Closure $next)
{
    if ($request->is('login') || $request->is('admin/auth/login')) {
        return $next($request);
    }

    if (Auth::guard('web')->check()) {
        $user = Auth::guard('web')->user();
        
        // Ensure this matches the controller and Fortify:
        if ($user->status !== 'active') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            return redirect()->route('login')->with('message', 'Your account is inactive.');
        }
    }
    return $next($request);
}
}