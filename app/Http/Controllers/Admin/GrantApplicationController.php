<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GrantApplication;
use App\Models\Settings;
use App\Models\User;
use App\Helpers\NotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class GrantApplicationController extends Controller
{
    /**
     * Display a listing of all grant applications.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $applications = GrantApplication::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        return view('admin.grant.index', compact('applications'));
    }

    /**
     * Display a listing of pending grant applications.
     *
     * @return \Illuminate\Http\Response
     */
    public function pending()
    {
        $applications = GrantApplication::with('user')
            ->where('status', 'processing')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        return view('admin.grant.pending', compact('applications'));
    }

    /**
     * Display a listing of approved grant applications.
     *
     * @return \Illuminate\Http\Response
     */
    public function approved()
    {
        $applications = GrantApplication::with('user')
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        return view('admin.grant.approved', compact('applications'));
    }

    /**
     * Display a listing of rejected grant applications.
     *
     * @return \Illuminate\Http\Response
     */
    public function rejected()
    {
        $applications = GrantApplication::with('user')
            ->where('status', 'rejected')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        return view('admin.grant.rejected', compact('applications'));
    }

    /**
     * Display a listing of disbursed grant applications.
     *
     * @return \Illuminate\Http\Response
     */
    public function disbursed()
    {
        $applications = GrantApplication::with('user')
            ->where('status', 'disbursed')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        return view('admin.grant.disbursed', compact('applications'));
    }

    /**
     * View a specific grant application.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function view($id)
    {
        $application = GrantApplication::with('user')->findOrFail($id);
        
        return view('admin.grant.view', compact('application'));
    }

    /**
     * Approve a grant application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function approve(Request $request, $id)
    {
        $application = GrantApplication::findOrFail($id);
        
        // Only allow approving applications that are in processing status
        if ($application->status !== 'processing') {
            return redirect()->route('admin.grants.view', $id)
                ->with('error', 'Only applications in processing status can be approved.');
        }
        
        $request->validate([
            'approved_amount' => 'required|numeric|min:0',
            'admin_note' => 'nullable|string|max:1000',
        ]);
        
        $application->status = 'approved';
        $application->approved_amount = $request->approved_amount;
        $application->save();
        
        // Send notification to user
        NotificationHelper::grantApplicationStatusUpdated($application->user, $application);
        
        // Add admin note if provided
        if ($request->admin_note) {
            // Append the admin note to the existing notes field
            $timestamp = Carbon::now()->format('Y-m-d H:i:s');
            $adminNote = "[ADMIN NOTE {$timestamp}] {$request->admin_note}\n\n";
            $application->notes = $adminNote . $application->notes;
            $application->save();
        }
        
        return redirect()->route('admin.grants.view', $id)
            ->with('success', 'Grant application has been approved successfully.');
    }

    /**
     * Reject a grant application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function reject(Request $request, $id)
    {
        $application = GrantApplication::findOrFail($id);
        
        // Only allow rejecting applications that are in processing status
        if ($application->status !== 'processing') {
            return redirect()->route('admin.grants.view', $id)
                ->with('error', 'Only applications in processing status can be rejected.');
        }
        
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);
        
        $application->status = 'rejected';
        $application->save();
        
        // Send notification to user
        NotificationHelper::grantApplicationStatusUpdated($application->user, $application);
        
        // Add rejection reason to the notes field
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        $rejectionNote = "[REJECTION {$timestamp}] {$request->rejection_reason}\n\n";
        $application->notes = $rejectionNote . $application->notes;
        $application->save();
        
        return redirect()->route('admin.grants.view', $id)
            ->with('success', 'Grant application has been rejected successfully.');
    }

    /**
     * Disburse funds to user's account balance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function disburse(Request $request, $id)
    {
        $application = GrantApplication::with('user')->findOrFail($id);
        
        // Only allow disbursing approved applications
        if ($application->status !== 'approved') {
            return redirect()->route('admin.grants.view', $id)
                ->with('error', 'Only approved applications can be disbursed.');
        }
        
        // Begin transaction to ensure both operations succeed or fail together
        \DB::beginTransaction();
        
        try {
            // Update user's account balance
            $user = $application->user;
            $user->account_bal += $application->approved_amount;
            $user->save();
            
            // Update application status
            $application->status = 'disbursed';
            $application->disbursal_date = Carbon::now();
            $application->save();
            
            // Add disbursement note to the application notes field
            $timestamp = Carbon::now()->format('Y-m-d H:i:s');
            $disbursementNote = "[DISBURSEMENT {$timestamp}] Grant funds of $" . number_format($application->approved_amount, 2) . " disbursed to user account.\n\n";
            $application->notes = $disbursementNote . $application->notes;
            $application->save();
            
            // Send notification to user
            NotificationHelper::grantFundsDisbursed($application->user, $application);
            
            \DB::commit();
            
            return redirect()->route('admin.grants.view', $id)
                ->with('success', 'Grant funds have been disbursed successfully to user account.');
        } catch (\Exception $e) {
            \DB::rollback();
            
            return redirect()->route('admin.grants.view', $id)
                ->with('error', 'An error occurred while disbursing funds: ' . $e->getMessage());
        }
    }

    /**
     * Delete a grant application.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete($id)
    {
        $application = GrantApplication::findOrFail($id);
        
        // No need to delete associated notes as they're stored in the application
        
        // Delete the application
        $application->delete();
        
        return redirect()->route('admin.grants.index')
            ->with('success', 'Grant application has been deleted successfully.');
    }

    /**
     * Add an admin note to a grant application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function addNote(Request $request, $id)
    {
        $application = GrantApplication::findOrFail($id);
        
        $request->validate([
            'admin_note' => 'required|string|max:1000',
        ]);
        
        // Add the admin note to the application notes field
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        $adminNote = "[ADMIN NOTE {$timestamp}] {$request->admin_note}\n\n";
        $application->notes = $adminNote . $application->notes;
        $application->save();
        
        return redirect()->route('admin.grants.view', $id)
            ->with('success', 'Note has been added successfully.');
    }
}
