<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAdminIsLoggedIIn
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
{
    if (Auth::guard('admin')->check()) {
        // Change this to redirect to dashboard if already logged in
        return redirect()->route('admin.dashboard');
    }
    return $next($request);
}
}
