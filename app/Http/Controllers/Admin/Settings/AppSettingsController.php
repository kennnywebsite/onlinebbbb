<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Settings;
use App\Models\SettingsCont;
use Illuminate\Support\Facades\Storage;

class AppSettingsController extends Controller
{
    /**
     * Get all app settings (Replaces appsettingshow)
     */
    public function getSettings()
    {
        return response()->json([
            'status' => 200,
            'data' => [
                'settings' => Settings::first(),
                'more_settings' => SettingsCont::first(),
                'timezones' => timezone_identifiers_list(),
            ]
        ]);
    }

    /**
     * Update website information
     */
    public function updateWebInfo(Request $request)
    {
        $request->validate([
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:500',
            'favicon' => 'nullable|image|mimes:jpg,jpeg,png,ico|max:500',
        ]);

        $settings = Settings::findOrFail(1);
        $data = $request->except(['logo', 'favicon']);

        // Handle file uploads
        if ($request->hasFile('logo')) {
            Storage::disk('public')->delete($settings->logo);
            $data['logo'] = $request->file('logo')->store('photos', 'public');
        }

        if ($request->hasFile('favicon')) {
            Storage::disk('public')->delete($settings->favicon);
            $data['favicon'] = $request->file('favicon')->store('photos', 'public');
        }

        $settings->update($data);
        
        // Update Additional Settings
        if ($request->has('purchase_code')) {
            SettingsCont::where('id', 1)->update(['purchase_code' => $request->purchase_code]);
        }

        return response()->json(['status' => 200, 'message' => 'Website information updated successfully.']);
    }

    /**
     * Update Transfer Security Codes
     */
    public function updateTransferCodes(Request $request)
    {
        Settings::where('id', 1)->update($request->only([
            'code1', 'code2', 'code3', 'code4', 'code5',
            'code1status', 'code2status', 'code3status', 'code4status', 'code5status',
            'code1message', 'code2message', 'code3message', 'code4message', 'code5message',
            'otp'
        ]));

        return response()->json(['status' => 200, 'message' => 'Transfer codes updated.']);
    }

    /**
     * Update Preferences
     */
    public function updatePreference(Request $request)
    {
        Settings::where('id', 1)->update([
            'contact_email' => $request->contact_email,
            'currency' => $request->currency,
            's_currency' => $request->s_currency,
            'enable_verification' => $request->boolean('enail_verify'),
            'return_capital' => $request->boolean('return_capital'),
            // Map remaining fields...
        ]);

        return response()->json(['status' => 200, 'message' => 'Preferences updated.']);
    }

    /**
     * Update Mail/SMTP Settings
     */
    public function updateEmailSettings(Request $request)
    {
        Settings::where('id', 1)->update($request->only([
            'mail_server', 'emailfrom', 'emailfromname', 'smtp_host', 'smtp_port',
            'smtp_encrypt', 'smtp_user', 'smtp_password', 'google_id', 
            'google_secret', 'capt_secret', 'capt_sitekey'
        ]));

        return response()->json(['status' => 200, 'message' => 'Email settings updated.']);
    }
}