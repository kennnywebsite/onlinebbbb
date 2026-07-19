<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        Log::info('EnsureIsAdmin: Checking admin access for path: ' . $request->path());

        if (Auth::guard('admin')->check()) {
            Log::info('EnsureIsAdmin: Admin authenticated. Access granted.');
            return $next($request);
        }

        Log::warning('EnsureIsAdmin: Admin NOT authenticated. Redirecting to adminloginform.');
        return redirect()->route('adminloginform');
    }
}