<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserStatus
{
    public function handle(Request $request, Closure $next)
    {
        // Allow access to login pages and any admin route
        if ($request->is('login') || $request->is('admin/auth/*') || Auth::guard('admin')->check()) {
            return $next($request);
        }

        if (Auth::guard('web')->check() && Auth::guard('web')->user()->status !== 'active') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            return redirect()->route('login')->with('message', 'Your account is inactive.');
        }

        return $next($request);
    }
}