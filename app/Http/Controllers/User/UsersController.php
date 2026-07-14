<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{User, Settings};
use App\Mail\NewNotification;
use Illuminate\Support\Facades\{Auth, Mail, Validator};

class UsersController extends Controller
{
    // API: Add username
    public function addusername(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'unique:users,username'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $user->update(['username' => $request->username]);

        return response()->json(['status' => 'success', 'message' => 'Username updated successfully.']);
    }

    // API: Send contact message
    public function sendcontact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
            'message' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $settings = Settings::where('id', '1')->first();
        $subject = "Inquiry from $request->name with email $request->email";

        Mail::to($settings->contact_email)->send(new NewNotification($request->message, $subject, 'Admin'));

        return response()->json(['status' => 'success', 'message' => 'Your message was sent successfully!']);
    }

    // API: Get Downlines as JSON (Instead of raw HTML rows)
    public function getdownlines(Request $request)
    {
        $user = Auth::user();
        $allUsers = User::all();
        
        // Return a clean array structure
        return response()->json([
            'data' => $this->formatDownlines($allUsers, $user->id)
        ]);
    }

    private function formatDownlines($array, $parent = 0, $level = 0)
    {
        $results = [];
        if ($level > 6) return $results;

        foreach ($array as $entry) {
            if ($entry->ref_by == $parent) {
                $results[] = [
                    'name' => $entry->name . ' ' . $entry->l_name,
                    'level' => ($level == 0) ? "Direct referral" : "Indirect referral level $level",
                    'parent' => $this->getUserParentName($entry->ref_by),
                    'status' => $entry->status,
                    'registered_at' => $entry->created_at,
                    'children' => $this->formatDownlines($array, $entry->id, $level + 1)
                ];
            }
        }
        return $results;
    }

    private function getUserParentName($id)
    {
        $parent = User::find($id);
        return $parent ? "{$parent->name} {$parent->l_name}" : "None";
    }
}