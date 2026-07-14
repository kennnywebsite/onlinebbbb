<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Kyc;
use App\Mail\NewNotification;
use App\Helpers\NotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class KycController extends Controller
{
    /**
     * Process KYC application via API
     */
    public function processKyc(Request $request)
    {
        $request->validate([
            'kyc_id' => 'required|exists:kycs,id',
            'action' => 'required|in:Accept,Reject',
            'message' => 'required|string',
            'subject' => 'required|string',
        ]);

        $application = Kyc::findOrFail($request->kyc_id);
        $user = User::findOrFail($application->user_id);

        if ($request->action === 'Accept') {
            $user->update(['account_verify' => 'Verified']);
            $application->update(['status' => 'Verified']);
            
            NotificationHelper::create(
                $user,
                'Your KYC verification has been approved. Your account is now fully verified.',
                'KYC Verification Approved',
                'success',
                'check-circle',
                '/account-verify' // API-friendly route
            );
        } else {
            // Delete images
            if (Storage::disk('public')->exists($application->frontimg)) {
                Storage::disk('public')->delete($application->frontimg);
            }
            if (Storage::disk('public')->exists($application->backimg)) {
                Storage::disk('public')->delete($application->backimg);
            }

            $user->update(['account_verify' => 'Rejected']);
            
            NotificationHelper::create(
                $user,
                'Your KYC verification was not approved. Please review requirements and re-submit.',
                'KYC Verification Rejected',
                'danger',
                'x-circle',
                '/account-verify'
            );
            
            $application->delete();
        }

        // Send Email
        Mail::to($user->email)->send(new NewNotification($request->message, $request->subject, $user->name));

        return response()->json([
            'status' => 'success',
            'message' => 'KYC processing action completed successfully.'
        ]);
    }
}