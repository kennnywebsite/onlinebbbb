<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\KycApplicationRequest;
use App\Mail\NewNotification;
use App\Models\Kyc;
use App\Models\Settings;
use App\Helpers\NotificationHelper;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class VerifyController extends Controller
{
    public function verifyaccount(KycApplicationRequest $request)
{
    $user = Auth::user();
    $whitelist = array('jpeg', 'jpg', 'png');
    $maxFileSize = 2 * 1024 * 1024; // 2MB in bytes

    // filter front of document upload
    $frontimg = $request->file('frontimg');
    $backimg = $request->file('backimg');
    $photo = $request->file('photo');
    
    // Check file sizes
    if ($frontimg->getSize() > $maxFileSize || $backimg->getSize() > $maxFileSize || 
        ($photo && $photo->getSize() > $maxFileSize)) {
        return redirect()->back()
            ->with('message', 'One or more uploaded files exceed the maximum allowed size of 2MB.');
    }
    
    $backimgExtention = $backimg->extension();
    $extension = $frontimg->extension();

    if (!in_array($extension, $whitelist) or !in_array($backimgExtention, $whitelist)) {
        return redirect()->back()
            ->with('message', 'Unaccepted Image Uploaded, please make sure to upload the correct document.');
    }

    // upload documents to storage
    $frontimgPath = $frontimg->store('uploads', 'public');
    $backimgPath = $backimg->store('uploads', 'public');

    $kyc = new Kyc();
    $kyc->title = $request->title;
    $kyc->gender = $request->gender;
    $kyc->zipcode = $request->zipcode;
    $kyc->dob = $request->dob;
    $kyc->statenumber = $request->statenumber;
    $kyc->accounttype = $request->accounttype;
    $kyc->income = $request->income;
    $kyc->kinname = $request->kinname;
    $kyc->kinaddress = $request->kinaddress;
    $kyc->relationship = $request->relationship;
    $kyc->employer = $request->employer;
    $kyc->address = $request->address;
    $kyc->city = $request->city;
    $kyc->state = $request->state;
    $kyc->country = $request->country;
    $kyc->document_type = $request->document_type;
    $kyc->frontimg = $frontimgPath;
    $kyc->backimg = $backimgPath;
    $kyc->status = 'Under review';
    $kyc->user_id = $user->id;
    $kyc->save();

    //update user
    User::where('id', $user->id)
        ->update([
            'kyc_id' => $kyc->id,
            'account_verify' => 'Under review',
            'dob'=> $request->dob,
            'address' =>  $request->address,
        ]);
        
    // Create notification for KYC submission
    NotificationHelper::create(
        $user,
        'Your KYC verification documents have been submitted successfully and are under review. You will be notified once the verification process is complete.',
        'KYC Verification Submitted',
        'info',
        'shield',
        route('account.verify')
    );
        
    $strtxt = $this->RandomStringGenerator(6);
    
    if($request->hasfile('photo')){
        $document1 = $request->file('photo');
        $filename1 = $document1->getClientOriginalName();
        $ext = array_pop(explode(".", $filename1));
        $whitelist = array('jpeg','jpg','png');

        if (in_array($ext, $whitelist)) {
            // Check file size for profile photo as well
            if ($document1->getSize() > $maxFileSize) {
                return redirect()->back()
                    ->with('message', 'Profile photo exceeds the maximum allowed size of 2MB.');
            }
            
            $cardname = $strtxt . $filename1 . time();
            // save to storage/app/uploads as the new $filename
            $path = $document1->storeAs('public/photos', $cardname);
        } else {
            $cardname = null;
        }
      
        //update user
        User::where('id', Auth::user()->id)
            ->update([
                'profile_photo_path' => $cardname,
            ]);
    }

    $settings = Settings::find(1);
    $message = "This is to inform you that $user->name just submitted a request for KYC(identity verification), please login your admin account to review and take neccessary action.";
    $subject = "Identity Verification Request from $user->name";
    $url = config('app.url') . '/admin/dashboard/kyc';
    Mail::to($settings->contact_email)->send(new NewNotification($message, $subject, 'Admin', $url));

    return redirect()->route('account.verify')->with('success', 'Action Sucessful! Please wait while we verify your application. You will receive an email regarding the status of your application.');
}
    
    
    function RandomStringGenerator($n) 
   { 
       $generated_string = ""; 
       $domain = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890"; 
       $len = strlen($domain); 
       for ($i = 0; $i < $n; $i++) 
       { 
           $index = rand(0, $len - 1); 
           $generated_string = $generated_string . $domain[$index]; 
       } 
       // Return the random generated string 
       return $generated_string; 
   } 
}
