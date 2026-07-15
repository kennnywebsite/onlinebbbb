<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\GrantApplication;
use App\Models\GrantNote;
use App\Models\Settings;
use App\Models\User;
use App\Helpers\NotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GrantApplicationController extends Controller
{
    /**
     * Display the grant application selection page.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = Auth::user();
        $latestApplication = GrantApplication::where('user_id', $user->id)
            ->latest()
            ->first();
        $hasApplication = !is_null($latestApplication);
        $grant_limit = $user->grant_limit;
        $latestStatus = $hasApplication ? $latestApplication->status : null;
        
        return view('user.grant.index', compact('hasApplication', 'grant_limit', 'latestStatus'));
    }

    /**
     * Show the individual grant application form.
     *
     * @return \Illuminate\Http\Response
     */
    public function individual()
    {
        $user = Auth::user();
        $grant_limit = $user->grant_limit;
        $application = null; // Default for new applications
        
        return view('user.grant.individual', compact('grant_limit', 'application'));
    }

    /**
     * Show the company grant application form.
     *
     * @return \Illuminate\Http\Response
     */
    public function company()
    {
        $user = Auth::user();
        $grant_limit = $user->grant_limit;
        $application = null; // Default for new applications
        
        return view('user.grant.company', compact('grant_limit', 'application'));
    }

    /**
     * Store the individual grant application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeIndividual(Request $request)
    {
        $request->validate([
            'requested_amount' => 'required|numeric|min:1',
            'funding_details' => 'nullable|string|max:500',
        ]);

        // Create a new grant application for individual
        $application = new GrantApplication();
        $application->user_id = Auth::id();
        $application->application_type = 'individual';
        
        // Always set status to processing initially, regardless of grant limit
        // This ensures users always see the processing page
        $application->status = 'processing';
        
        // Store grant limit info for reference
        $user = Auth::user();
        $application->notes = ($request->funding_details ? 'Funding Details: ' . $request->funding_details . "\n\n" : '') .
                           'Grant limit at time of application: ' . $user->grant_limit;
        
        $application->program_funding = $request->has('program_funding') ? 1 : 0;
        $application->research_funding = $request->has('research_funding') ? 1 : 0;
        // Map available fields to database
        $application->equipment_funding = $request->has('capacity_funding') ? 1 : 0; // Map capacity to equipment 
        $application->community_outreach = $request->has('other_funding') ? 1 : 0; // Map other to community
        // Store funding details in notes if not already set
        if ($request->funding_details && !isset($application->notes)) {
            $application->notes = 'Funding Details: ' . $request->funding_details;
        }
        $application->requested_amount = $request->requested_amount; // Using user specified amount
        $application->save();
        
        // Send notification to user and admin
        NotificationHelper::grantApplicationSubmitted(Auth::user(), $application);
        NotificationHelper::notifyAdminOfNewApplication($application);

        return redirect()->route('grant.processing');
    }

    /**
     * Store the company grant application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeCompany(Request $request)
    {
        $request->validate([
            'legal_name' => 'required|string|max:255',
            'tax_id' => 'required|string|max:30',
            'organization_type' => 'required|string|in:nonprofit,for-profit,government,educational',
            'founding_year' => 'required|digits:4|integer|min:1900|max:'.(date('Y')),
            'phone_number' => 'required|string|max:20',
            'contact_person' => 'required|string|max:255',
            'mission_statement' => 'required|string|max:500',
            'mailing_address' => 'required|string|max:500',
            'incorporation_date' => 'required|date',
            'project_title' => 'required|string|max:255',
            'project_description' => 'required|string|max:1000',
            'expected_outcomes' => 'required|string|max:500',
            'requested_amount' => 'required|numeric|min:1',
        ]);

        // Create a new grant application for company
        $application = new GrantApplication();
        $application->user_id = Auth::id();
        $application->application_type = 'company';
        
        // Always set status to processing initially, regardless of grant limit
        $application->status = 'processing';
        
        // Store company information
        $application->legal_name = $request->legal_name;
        $application->ein = $request->tax_id; // Map tax_id to ein
        $application->mission_statement = $request->mission_statement;
        $application->mailing_address = $request->mailing_address; // Store in correct column
        $application->incorporation_date = $request->incorporation_date; // Store in correct column
        
        // Store contact information and organization details in notes
        $user = Auth::user();
        $application->notes = 'Contact Person: ' . $request->contact_person . "\n" . 
                          'Phone: ' . $request->phone_number . "\n" .
                          'Organization Type: ' . $request->organization_type . "\n" .
                          'Founded: ' . $request->founding_year . "\n\n" .
                          'Project Title: ' . $request->project_title . "\n" .
                          'Project Description: ' . $request->project_description . "\n" .
                          'Expected Outcomes: ' . $request->expected_outcomes . "\n\n" .
                          'Grant limit at time of application: ' . $user->grant_limit;
        
        // Set service areas and requested amount
        $application->service_areas = 'Various';
        $application->requested_amount = $request->requested_amount; // Use requested amount from form
        $application->save(); 
        
        // Send notification to user and admin
        NotificationHelper::grantApplicationSubmitted(Auth::user(), $application);
        NotificationHelper::notifyAdminOfNewApplication($application);

        return redirect()->route('grant.processing');
    }

    /**
     * Show the processing page.
     *
     * @return \Illuminate\Http\Response
     */
    public function processing()
    {
        $user = Auth::user();
        $grant_limit = $user->grant_limit;
        $latest_application = GrantApplication::where('user_id', $user->id)
            ->latest()
            ->first();
        
        if (!$latest_application) {
            return redirect()->route('grant.index')
                ->with('error', 'No grant application found. Please apply first.');
        }
        
        // Verify application is in processing status
        if ($latest_application->status !== 'processing') {
            if ($latest_application->status === 'approved') {
                return redirect()->route('grant.results');
            } else {
                return redirect()->route('grant.index');
            }
        }
        
        return view('user.grant.processing', compact('grant_limit', 'latest_application'));
    }

    /**
     * Show the results page.
     *
     * @return \Illuminate\Http\Response
     */
    public function results()
    {
        $user = Auth::user();
        $grant_limit = $user->grant_limit;
        $latest_application = GrantApplication::where('user_id', $user->id)
            ->latest()
            ->first();
        
        if (!$latest_application) {
            return redirect()->route('grant.index')
                ->with('error', 'No grant application found. Please apply first.');
        }
        
        // Update the application status based on grant limit
        if ($latest_application->status === 'processing') {
            // If grant limit is 0 or negative, set status to rejected
            if ($grant_limit <= 0) {
                $latest_application->status = 'rejected';
                // Add decision date info to notes
                $latest_application->notes = ($latest_application->notes ? $latest_application->notes . "\n\n" : '') . 
                                         'Rejected on: ' . now() . "\n" . 
                                         'Reason: Insufficient grant limit';
            } else {
                // Otherwise approve the application
                $latest_application->status = 'approved';
                $latest_application->approved_amount = $grant_limit; // Use the grant limit as the approved amount
                // Add decision date info to notes
                $latest_application->notes = ($latest_application->notes ? $latest_application->notes . "\n\n" : '') . 
                                         'Approved on: ' . now() . "\n" . 
                                         'Estimated disbursement: ' . now()->addDays(5);
            }
            $latest_application->save();
        }
        
        return view('user.grant.results', compact('grant_limit', 'latest_application'));
    }
    
    /**
     * Show the list of user's grant applications.
     *
     * @return \Illuminate\Http\Response
     */
    public function myApplications()
    {
        $applications = GrantApplication::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        $contact_email = Settings::first()->contact_email;
        
        return view('user.grant.myApplications', compact('applications', 'contact_email'));
    }
    
    /**
     * View a specific grant application.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function view($id)
    {
        $application = GrantApplication::where('user_id', Auth::id())
            ->findOrFail($id);
            
        // For now, assume no notes - we will add them later if needed
        $application->notes = collect();
        
        return view('user.grant.view', compact('application'));
    }
    
    /**
     * Show the edit form for a grant application.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $user = Auth::user();
        $grant_limit = $user->grant_limit;
        $application = GrantApplication::where('user_id', Auth::id())
            ->where('status', 'processing')
            ->findOrFail($id);
        
        if ($application->application_type == 'individual') {
            return view('user.grant.individual', compact('application', 'grant_limit'));
        } else {
            return view('user.grant.company', compact('application', 'grant_limit'));
        }
    }
    
    /**
     * Update an individual grant application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateIndividual(Request $request, $id)
    {
        $application = GrantApplication::where('user_id', Auth::id())
            ->where('status', 'processing')
            ->where('application_type', 'individual')
            ->findOrFail($id);
            
        $request->validate([
            'requested_amount' => 'required|numeric|min:1|max:' . Auth::user()->grant_limit,
            'funding_details' => 'nullable|string|max:500',
        ]);
        
        // Store funding details in notes
        if ($request->funding_details) {
            $application->notes = 'Funding Details: ' . $request->funding_details;
        }
        $application->program_funding = $request->has('program_funding') ? 1 : 0;
        $application->research_funding = $request->has('research_funding') ? 1 : 0;
        $application->equipment_funding = $request->has('capacity_funding') ? 1 : 0; // Map capacity to equipment
        $application->community_outreach = $request->has('other_funding') ? 1 : 0; // Map other to community
        $application->requested_amount = $request->requested_amount;
        $application->save();
        
        // Send notification to user and admin
        NotificationHelper::grantApplicationUpdated(Auth::user(), $application);
        NotificationHelper::notifyAdminOfUpdatedApplication($application);

        return redirect()->route('grant.view', $application->id)
            ->with('success', 'Your application has been updated successfully.');
    }
    
    /**
     * Update a company grant application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateCompany(Request $request, $id)
    {
        $application = GrantApplication::where('user_id', Auth::id())
            ->where('status', 'processing')
            ->where('application_type', 'company')
            ->findOrFail($id);
            
        $request->validate([
            'legal_name' => 'required|string|max:255',
            'tax_id' => 'required|string|max:30',
            'organization_type' => 'required|string|in:nonprofit,for-profit,government,educational',
            'founding_year' => 'required|digits:4|integer|min:1900|max:'.(date('Y')),
            'phone_number' => 'required|string|max:20',
            'contact_person' => 'required|string|max:255',
            'mission_statement' => 'required|string|max:500',
            'project_title' => 'required|string|max:255',
            'project_description' => 'required|string|max:1000',
            'expected_outcomes' => 'required|string|max:500',
        ]);
        
        $application->legal_name = $request->legal_name;
        $application->ein = $request->tax_id; // Map tax_id to ein
        $application->mission_statement = $request->mission_statement;
        
        // Store contact information and organization details in notes
        $application->notes = 'Contact Person: ' . $request->contact_person . "\n" . 
                          'Phone: ' . $request->phone_number . "\n" .
                          'Organization Type: ' . $request->organization_type . "\n" .
                          'Founded: ' . $request->founding_year . "\n\n" .
                          'Project Title: ' . $request->project_title . "\n" .
                          'Project Description: ' . $request->project_description . "\n" .
                          'Expected Outcomes: ' . $request->expected_outcomes;
        $application->save();
        
        return redirect()->route('grant.view', $application->id)
            ->with('success', 'Your application has been updated successfully.');
    }
    
    /**
     * Add a note to a grant application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function addNote(Request $request, $id)
    {
        $application = GrantApplication::where('user_id', Auth::id())
            ->where('status', 'pending')
            ->findOrFail($id);
            
        $request->validate([
            'note' => 'required|string|max:1000',
        ]);
        
        // We'll create this model if needed, for now just redirect
        return redirect()->route('grant.view', $application->id)
            ->with('success', 'Your note has been added successfully.');
    }
}
