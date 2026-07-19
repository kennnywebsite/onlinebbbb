<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Models\Admin;
use App\Models\Settings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Mail\Twofa;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log; // Added for Logging

class LoginController extends Controller
{
    /**
     * Show the login form.
     * 
     * @return \Illuminate\Http\Response
     */
    public function showLoginForm()
    {
        return view('auth.adminlogin',[
            'title' => 'Admin Login',
            'settings' => Settings::where('id', '=', '1')->first(),
        ]);
    }

    /**
     * Login the admin.
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function adminlogin(Request $request)
    {
        $this->validate($request, [
            'email'    => 'required|email|exists:admins|min:5|max:191',
            'password' => 'required|string|min:4|max:255',
        ]);

        $credentials = $request->only('email', 'password');
        $credentials['status'] = 'active';

        if (Auth::guard('admin')->attempt($credentials)) {
            // Log successful attempt
            Log::info('Admin login successful for: ' . $request->email);
            
            $request->session()->regenerate();

            $user = Auth::guard('admin')->user();
            $settings = Settings::where('id', '=', '1')->first();

            // Handle 2FA Logic
            if ($user->enable_2fa == "enabled") {
                $token  = mt_rand(10000, 99999);
                $user->update([
                    'token_2fa' => $token,
                    'pass_2fa' => 'false',
                ]);      
                
                $objDemo = new \stdClass();
                $objDemo->message = $token;
                $objDemo->sender = $settings->site_name ?? 'Admin';
                $objDemo->subject = "Two Factor Code";
                $objDemo->date = \Carbon\Carbon::now();
                
                Mail::bcc($user->email)->send(new Twofa($objDemo));
                
                return redirect()->intended('/admin/2fa');
            } 

            return redirect()->intended('admin/dashboard');
        }
        
        // Log failed attempt
        Log::warning('Login failed for: ' . $request->email);
        
        return back()->withErrors([
            'email' => 'The provided credentials do not match our records or account is inactive.',
        ]);
    }

    public function validate_admin()
{
    // Because this method is protected by 'isadmin' middleware, 
    // we know the user is already authenticated.
    return view('admin.dashboard'); // Or whatever your view file is named
}

    /**
     * Logout the admin.
     * 
     * @return \Illuminate\Http\RedirectResponse
     */
    public function adminlogout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()
            ->route('adminloginform')
            ->with('status','Admin has been logged out!');
    }
}