<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use App\Mail\NewNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;

class ManageAdminController extends Controller
{
    // Block admin
    public function blockadmin($id) {
        Admin::findOrFail($id)->update(['acnt_type_active' => 'blocked']);
        return response()->json(['status' => 'success', 'message' => 'Manager Blocked']);
    }

    // Unblock admin
    public function unblockadmin($id) {
        Admin::findOrFail($id)->update(['acnt_type_active' => 'active']);
        return response()->json(['status' => 'success', 'message' => 'Manager Unblocked']);
    }

    // Reset Password
    public function resetadpwd($id) {
        Admin::findOrFail($id)->update(['password' => Hash::make('admin01236')]);
        return response()->json(['status' => 'success', 'message' => 'Password reset to default.']);
    }

    public function deleteadminacnt($id) {
        Admin::findOrFail($id)->delete();
        return response()->json(['status' => 'success', 'message' => 'Manager deleted successfully']);
    }

    // Update admin info
    public function editadmin(Request $request) {
        $request->validate(['user_id' => 'required', 'email' => 'email']);
        Admin::findOrFail($request->user_id)->update([
            'firstName' => $request->fname,
            'lastName'  => $request->l_name,
            'email'     => $request->email,
            'phone'     => $request->phone,
            'type'      => $request->type,
        ]);
        return response()->json(['status' => 'success', 'message' => 'Account updated successfully']);
    }

    // Send mail to one user
    public function sendmail(Request $request) {
        $admin = Admin::findOrFail($request->user_id);
        Mail::to($admin->email)->send(new NewNotification($request->message, $request->subject, $admin->firstName));
        return response()->json(['status' => 'success', 'message' => 'Message sent successfully']);
    }

    // Update Password (authenticated admin)
    public function adminupdatepass(Request $request) {
        $admin = Auth::guard('admin')->user();
        
        if (!Hash::check($request->old_password, $admin->password)) {
            return response()->json(['status' => 'error', 'message' => 'Incorrect Old Password'], 400);
        }

        $request->validate([
            'password' => 'required|min:8|confirmed',
        ]);

        $admin->update(['password' => Hash::make($request->password)]);
        return response()->json(['status' => 'success', 'message' => 'Password changed successfully']);
    }

    // Change style
    public function changestyle(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        $admin->update(['dashboard_style' => $request->boolean('style') ? 'dark' : 'light']);
        return response()->json(['status' => 'success', 'message' => 'Style updated']);
    }

    // Add new admin
    public function saveadmin(Request $request) {
        $request->validate([
            'fname' => 'required',
            'email' => 'required|email|unique:admins',
            'password' => 'required|min:8|confirmed',
        ]);
    
        Admin::create([
            'firstName' => $request->fname,
            'lastName'  => $request->l_name,
            'email'     => $request->email,
            'phone'     => $request->phone,
            'type'      => $request->type,
            'acnt_type_active' => 'active',
            'status'    => 'active',
            'password'  => Hash::make($request->password),
        ]);
        
        return response()->json(['status' => 'success', 'message' => 'Manager added successfully'], 201);
    }

    // Update own profile
    public function updateadminprofile(Request $request) {
        Auth::guard('admin')->user()->update([
          'firstName'  => $request->name,
          'lastName'   => $request->lname,
          'phone'      => $request->phone,
          'enable_2fa' => $request->token,
        ]);
        return response()->json(['status' => 'success', 'message' => 'Profile updated']);
    }
}