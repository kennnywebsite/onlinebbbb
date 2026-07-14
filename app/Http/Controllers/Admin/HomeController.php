<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\{User, Plans, Mt4Details, Withdrawal, Deposit, Kyc, Task, Admin};
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Dashboard Data
     */
    public function dashboard()
    {
        return response()->json([
            'status' => 200,
            'data' => [
                'deposits' => [
                    'total_processed' => Deposit::where('status', 'Processed')->sum('amount'),
                    'total_pending' => Deposit::where('status', 'Pending')->sum('amount'),
                ],
                'withdrawals' => [
                    'total_processed' => Withdrawal::where('status', 'Processed')->sum('amount'),
                    'total_pending' => Withdrawal::where('status', 'Pending')->sum('amount'),
                ],
                'users' => [
                    'total' => User::count(),
                    'active' => User::where('status', 'active')->count(),
                    'blocked' => User::where('status', 'blocked')->count(),
                    'unverified' => User::where('account_verify', '!=', 'yes')->count(),
                ],
                'stats' => [
                    'plans_count' => Plans::count(),
                    'total_transactions' => \App\Models\Tp_Transaction::sum('amount'),
                ]
            ]
        ]);
    }

    /**
     * Get All Plans
     */
    public function getPlans()
    {
        return response()->json([
            'status' => 200,
            'data' => [
                'main_plans' => Plans::where('type', 'Main')->orderBy('created_at', 'ASC')->get(),
                'promo_plans' => Plans::where('type', 'Promo')->get(),
            ]
        ]);
    }

    /**
     * Fetch all relevant data for User Management
     */
    public function getWithdrawals()
    {
        return response()->json([
            'status' => 200,
            'data' => Withdrawal::with('duser')->orderBy('id', 'desc')->get()
        ]);
    }

    /**
     * Fetch all relevant data for Deposits
     */
    public function getDeposits()
    {
        return response()->json([
            'status' => 200,
            'data' => Deposit::with('duser')->orderBy('id', 'desc')->get()
        ]);
    }

    /**
     * Generate codes for new user creation
     */
    public function getNewUserDefaults()
    {
        return response()->json([
            'status' => 200,
            'data' => [
                'usernumber' => $this->RandomStringGenerator(11),
                'code1' => $this->RandomStringGenerator(7),
                'code2' => $this->RandomStringGenerator(7),
                'code3' => $this->RandomStringGenerator(7),
                'pin' => $this->RandomStringGenerator(4),
            ]
        ]);
    }

    /**
     * KYC List
     */
    public function getKycs()
    {
        return response()->json([
            'status' => 200,
            'data' => Kyc::with('user')->orderByDesc('id')->get()
        ]);
    }

    /**
     * Get Tasks for the authenticated admin
     */
    public function getMyTasks(Request $request)
    {
        $adminId = $request->user()->id; // Assuming Sanctum auth
        return response()->json([
            'status' => 200,
            'data' => Task::where('designation', $adminId)->orderByDesc('id')->get()
        ]);
    }

    /**
     * Helper: Random String Generator
     */
    private function RandomStringGenerator($n)
    {
        $generated_string = "";
        $domain = "12345678900123456789023456789034567890456789056789067890890";
        for ($i = 0; $i < $n; $i++) {
            $generated_string .= $domain[rand(0, strlen($domain) - 1)];
        }
        return $generated_string;
    }
}