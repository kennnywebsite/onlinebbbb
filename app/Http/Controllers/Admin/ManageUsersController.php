<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\{User, Activity, User_plans, Tp_Transaction, Deposit, Withdrawal, Agent, Kyc, Settings};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, DB, Auth};
use App\Helpers\NotificationHelper;

class ManageUsersController extends Controller
{
    /**
     * Get all users
     */
    public function index()
    {
        return response()->json(['status' => 200, 'data' => User::latest()->get()]);
    }

    /**
     * Get single user details
     */
    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json(['status' => 200, 'data' => $user]);
    }

    /**
     * Manage User Status (Block/Unblock/Dormant)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:active,blocked,inactive']);
        
        User::where('id', $id)->update(['status' => $request->status]);
        
        return response()->json(['status' => 200, 'message' => "User status updated to {$request->status}"]);
    }

    /**
     * Clear User Financials
     */
    public function clearAccount(Request $request, $id)
    {
        User::where('id', $id)->update([
            'account_bal' => 0,
            'roi' => 0,
            'bonus' => 0,
            'ref_bonus' => 0,
            'btc_balance' => 0
        ]);

        return response()->json(['status' => 200, 'message' => 'User financial records cleared.']);
    }

    /**
     * Edit User Information
     */
    public function updateProfile(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->update($request->all());

        return response()->json(['status' => 200, 'message' => 'User profile updated successfully.']);
    }

    /**
     * Create New User (API Style)
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:255',
            'username' => 'required|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'account_bal' => $request->balance ?? 0,
            'account_verify' => 'Verified'
        ]);

        return response()->json(['status' => 201, 'message' => 'User created successfully', 'data' => $user]);
    }

    /**
     * Mark Loan/Plan Status
     */
    public function updatePlanStatus(Request $request, $id)
    {
        $plan = User_plans::findOrFail($id);
        $plan->update(['active' => $request->status]);

        if ($request->status == 'Processed') {
            $user = User::find($plan->user);
            $user->increment('account_bal', $plan->amount);
            
            Tp_Transaction::create([
                'user' => $user->id,
                'plan' => 'Loan',
                'amount' => $plan->amount,
                'type' => 'Loan Credit'
            ]);
        }

        return response()->json(['status' => 200, 'message' => 'Plan status updated successfully.']);
    }

    /**
     * Delete User (Cascading deletion)
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        // Use database transactions to ensure consistency
        DB::transaction(function () use ($user, $id) {
            Deposit::where('user', $id)->delete();
            Withdrawal::where('user', $id)->delete();
            User_plans::where('user', $id)->delete();
            $user->delete();
        });

        return response()->json(['status' => 200, 'message' => 'User and associated data deleted.']);
    }
}