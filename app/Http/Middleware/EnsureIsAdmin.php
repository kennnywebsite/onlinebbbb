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
    if (Auth::guard('admin')->check()) {
        return $next($request);
    } 
    
    // LOG THE FAILURE TO SEE WHY IT'S REJECTING YOU
    \Log::error('Admin Auth Check Failed. Guard: admin. User: ' . Auth::guard('admin')->user());
    
    return redirect()->route('adminloginform')->with('message', 'Session expired or not authorized.');
}
}
