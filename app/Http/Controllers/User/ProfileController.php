<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    // Updating Profile Route
    public function updateprofile(Request $request)
    {
        $request->user()->update($request->only(['name', 'dob', 'phone', 'address']));
        
        return response()->json(['status' => 200, 'message' => 'Profile Information Updated Successfully!']);
    }

    // Update account and contact info
    public function updateacct(Request $request)
    {
        $request->user()->update([
            'bank_name'      => $request->bank_name,
            'account_name'   => $request->account_name,
            'account_number' => $request->account_no,
            'swift_code'     => $request->swiftcode,
            'btc_address'    => $request->btc_address,
            'eth_address'    => $request->eth_address,
            'ltc_address'    => $request->ltc_address,
            'usdt_address'   => $request->usdt_address,
        ]);
        
        return response()->json(['status' => 200, 'message' => 'Withdrawal Info updated Successfully']);
    }

    // Update Password
    public function updatepass(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if (!Hash::check($request->current_password, $request->user()->password)) {
            return response()->json(['status' => 400, 'message' => 'Current password does not match!'], 400);
        }

        $request->user()->update(['password' => Hash::make($request->password)]);
        
        return response()->json(['status' => 200, 'message' => 'Password updated successfully']);
    }

    // Change Pin
    public function changepin(Request $request)
    {
        $request->validate(['current_password' => 'required', 'pin' => 'required']);

        if (!Hash::check($request->current_password, $request->user()->password)) {
            return response()->json(['status' => 400, 'message' => 'Password does not match!'], 400);
        }

        $request->user()->update(['pin' => $request->pin]);

        return response()->json(['status' => 200, 'message' => 'Transaction Pin Updated Successfully']);
    }

    // Update email preference
    public function updateemail(Request $request)
    {
        $request->user()->update([
            'sendotpemail'     => $request->otpsend,
            'sendroiemail'     => $request->roiemail,
            'sendinvplanemail' => $request->invplanemail,
        ]);
        
        return response()->json(['status' => 200, 'message' => 'Email Preference updated']);
    }

    // Update Profile photo
    public function updateprofilephoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:4000',
        ]);
        
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $filename = $this->RandomStringGenerator(6) . time() . '.' . $file->getClientOriginalExtension();
            
            // Store file
            $path = $file->storeAs('public/photos', $filename);
            
            // Update user
            $request->user()->update(['profile_photo_path' => $filename]);

            return response()->json([
                'status' => 200, 
                'message' => 'Profile Photo Uploaded Successfully.',
                'path' => Storage::url($path)
            ]);
        }

        return response()->json(['status' => 400, 'message' => 'No file uploaded'], 400);
    }

    private function RandomStringGenerator($n) 
    { 
        return substr(str_shuffle(str_repeat("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890", $n)), 0, $n);
    } 
}