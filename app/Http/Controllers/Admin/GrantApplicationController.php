<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\GrantApplication;
use App\Helpers\NotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GrantApplicationController extends Controller
{
    /**
     * Get applications filtered by status
     */
    public function index(Request $request)
    {
        $status = $request->query('status'); // e.g., 'processing', 'approved'
        $query = GrantApplication::with('user')->orderByDesc('created_at');
        
        if ($status) {
            $query->where('status', $status);
        }
        
        return response()->json(['status' => 200, 'data' => $query->paginate(15)]);
    }

    /**
     * View specific application
     */
    public function view($id)
    {
        return response()->json(['status' => 200, 'data' => GrantApplication::with('user')->findOrFail($id)]);
    }

    /**
     * Approve Application
     */
    public function approve(Request $request, $id)
    {
        $request->validate(['approved_amount' => 'required|numeric|min:0']);
        $application = GrantApplication::findOrFail($id);

        if ($application->status !== 'processing') {
            return response()->json(['message' => 'Only processing applications can be approved.'], 400);
        }

        $application->update([
            'status' => 'approved',
            'approved_amount' => $request->approved_amount,
            'notes' => "[ADMIN NOTE " . now() . "] " . $request->admin_note . "\n\n" . $application->notes
        ]);

        NotificationHelper::grantApplicationStatusUpdated($application->user, $application);
        
        return response()->json(['status' => 200, 'message' => 'Application approved successfully.']);
    }

    /**
     * Reject Application
     */
    public function reject(Request $request, $id)
    {
        $request->validate(['rejection_reason' => 'required|string']);
        $application = GrantApplication::findOrFail($id);

        if ($application->status !== 'processing') {
            return response()->json(['message' => 'Only processing applications can be rejected.'], 400);
        }

        $application->update([
            'status' => 'rejected',
            'notes' => "[REJECTION " . now() . "] " . $request->rejection_reason . "\n\n" . $application->notes
        ]);

        NotificationHelper::grantApplicationStatusUpdated($application->user, $application);

        return response()->json(['status' => 200, 'message' => 'Application rejected.']);
    }

    /**
     * Disburse Funds
     */
    public function disburse(Request $request, $id)
    {
        $application = GrantApplication::with('user')->findOrFail($id);
        
        if ($application->status !== 'approved') {
            return response()->json(['message' => 'Only approved applications can be disbursed.'], 400);
        }

        DB::beginTransaction();
        try {
            $user = $application->user;
            $user->increment('account_bal', $application->approved_amount);
            
            $application->update([
                'status' => 'disbursed',
                'disbursal_date' => now(),
                'notes' => "[DISBURSEMENT " . now() . "] Funds disbursed.\n\n" . $application->notes
            ]);

            NotificationHelper::grantFundsDisbursed($user, $application);
            DB::commit();

            return response()->json(['status' => 200, 'message' => 'Funds disbursed successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Disbursement failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete Application
     */
    public function delete($id)
    {
        GrantApplication::findOrFail($id)->delete();
        return response()->json(['status' => 200, 'message' => 'Application deleted.']);
    }
}