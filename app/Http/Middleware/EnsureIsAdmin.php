<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Admin;
use App\Models\Settings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\meta;
class EnsureIsAdmin
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
    // Debugging: If this log doesn't show in storage/logs/laravel.log, 
    // the middleware isn't even being hit.
    if (Auth::guard('admin')->check()) {
        return $next($request);
    } 
    
    // If we are here, the guard is NOT checked. 
    // Instead of redirecting to a route that might redirect back, 
    // just redirect to login form directly.
    return redirect()->route('adminloginform')->with('message', 'Access Denied.');
}
}
