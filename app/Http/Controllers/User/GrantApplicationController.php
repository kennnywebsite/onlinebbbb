<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GrantApplication;
use App\Models\Settings;
use App\Helpers\NotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GrantApplicationController extends Controller
{
    /**
     * Get dashboard status for grant application.
     */
    public function index()
    {
        $user = Auth::user();
        $latest = GrantApplication::where('user_id', $user->id)->latest()->first();
        
        return response()->json([
            'status' => 200,
            'data' => [
                'has_application' => !is_null($latest),
                'grant_limit' => $user->grant_limit,
                'latest_status' => $latest ? $latest->status : null,
                'application' => $latest
            ]
        ]);
    }

    public function storeIndividual(Request $request)
    {
        $request->validate([
            'requested_amount' => 'required|numeric|min:1',
            'funding_details' => 'nullable|string|max:500',
        ]);

        $application = GrantApplication::create([
            'user_id' => Auth::id(),
            'application_type' => 'individual',
            'status' => 'processing',
            'requested_amount' => $request->requested_amount,
            'program_funding' => $request->has('program_funding') ? 1 : 0,
            'research_funding' => $request->has('research_funding') ? 1 : 0,
            'equipment_funding' => $request->has('capacity_funding') ? 1 : 0,
            'community_outreach' => $request->has('other_funding') ? 1 : 0,
            'notes' => ($request->funding_details ? 'Funding Details: ' . $request->funding_details : '') . 
                       "\nGrant limit: " . Auth::user()->grant_limit,
        ]);

        NotificationHelper::grantApplicationSubmitted(Auth::user(), $application);
        NotificationHelper::notifyAdminOfNewApplication($application);

        return response()->json(['status' => 200, 'message' => 'Application submitted.', 'data' => $application]);
    }

    public function storeCompany(Request $request)
    {
        $request->validate([
            'legal_name' => 'required|string|max:255',
            'tax_id' => 'required|string|max:30',
            'organization_type' => 'required|in:nonprofit,for-profit,government,educational',
            'project_title' => 'required|string|max:255',
            'requested_amount' => 'required|numeric',
        ]);

        $application = GrantApplication::create([
            'user_id' => Auth::id(),
            'application_type' => 'company',
            'status' => 'processing',
            'legal_name' => $request->legal_name,
            'ein' => $request->tax_id,
            'requested_amount' => $request->requested_amount,
            'notes' => 'Contact: ' . $request->contact_person . "\nProject: " . $request->project_title,
        ]);

        NotificationHelper::grantApplicationSubmitted(Auth::user(), $application);
        NotificationHelper::notifyAdminOfNewApplication($application);

        return response()->json(['status' => 200, 'message' => 'Company application submitted.', 'data' => $application]);
    }

    public function results()
    {
        $user = Auth::user();
        $latest = GrantApplication::where('user_id', $user->id)->latest()->first();

        if (!$latest) return response()->json(['message' => 'No application found'], 404);

        if ($latest->status === 'processing') {
            $latest->status = ($user->grant_limit <= 0) ? 'rejected' : 'approved';
            $latest->approved_amount = ($latest->status === 'approved') ? $user->grant_limit : 0;
            $latest->save();
        }

        return response()->json(['status' => 200, 'data' => $latest]);
    }

    public function myApplications()
    {
        $apps = GrantApplication::where('user_id', Auth::id())->orderByDesc('created_at')->paginate(10);
        return response()->json(['status' => 200, 'data' => $apps]);
    }

    public function view($id)
    {
        $application = GrantApplication::where('user_id', Auth::id())->findOrFail($id);
        return response()->json(['status' => 200, 'data' => $application]);
    }
}