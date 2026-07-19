<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
{
    if (! $request->expectsJson()) {
        // If the request path starts with admin, send them to the admin login
        if ($request->is('admin*')) {
            return route('adminloginform'); 
        }
        return route('login');
    }
}
}
