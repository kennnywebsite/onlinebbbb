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
            \Log::info('Admin login successful for: ' . $request->email);
            
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
        \Log::warning('Login failed for: ' . $request->email);
        
        return back()->withErrors([
            'email' => 'The provided credentials do not match our records or account is inactive.',
        ]);
    }