<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Models\Admin;
use App\Models\Settings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Mail\Twofa;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.adminlogin', [
            'title' => 'Admin Login',
            'settings' => Settings::where('id', '1')->first(),
        ]);
    }

    public function adminlogin(Request $request)
    {
        // 1. Validation
        $request->validate([
            'email'    => 'required|email|exists:admins|min:5|max:191',
            'password' => 'required|string|min:4|max:255',
        ]);

        $credentials = [
            'email' => $request->email, 
            'password' => $request->password, 
            'status' => 'active' // Ensure your DB column exactly matches this
        ];

        Log::info('Login attempt for: ' . $request->email);

        // 2. Attempt Authentication
        if (Auth::guard('admin')->attempt($credentials)) {
            $request->session()->regenerate();
            $admin = Auth::guard('admin')->user();
            
            Log::info('Login successful for ID: ' . $admin->id);

            // 3. 2FA Logic
            if ($admin->enable_2fa == "enabled") {
                $token = mt_rand(10000, 99999);
                $admin->update(['token_2fa' => $token, 'pass_2fa' => 'false']);
                
                $settings = Settings::where('id', '1')->first();
                $objDemo = new \stdClass();
                $objDemo->message = $token;
                $objDemo->sender = $settings->site_name ?? 'Admin';
                $objDemo->subject = "Two Factor Code";
                $objDemo->date = \Carbon\Carbon::now();
                
                Mail::to($admin->email)->send(new Twofa($objDemo));
                
                return redirect('/admin/2fa');
            }

            return redirect()->route('admin.dashboard');
        }

        // 4. Failed Login
        Log::warning('Login failed for: ' . $request->email);
        
        return back()->withErrors([
            'email' => 'Invalid credentials or account is not active.',
        ])->withInput($request->only('email'));
    }

    public function adminlogout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('adminloginform')->with('status', 'Logged out successfully!');
    }
}