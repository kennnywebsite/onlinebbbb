<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\{Kyc, Settings, User};
use App\Helpers\NotificationHelper;
use App\Mail\NewNotification;
use Illuminate\Support\Facades\{Auth, Mail, Validator};
use Illuminate\Http\Request;

class VerifyController extends Controller
{

    public function verifyaccount(Request $request)
    {

        // API Validation
        $validator = Validator::make($request->all(), [

            'frontimg' => 'required|image|mimes:jpeg,jpg,png|max:2048',
            'backimg'  => 'required|image|mimes:jpeg,jpg,png|max:2048',
            'photo'    => 'nullable|image|mimes:jpeg,jpg,png|max:2048',

            'title' => 'required',
            'gender' => 'required',
            'dob' => 'required',
            'address' => 'required',

        ]);


        if ($validator->fails()) {

            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ],422);

        }


        $user = Auth::user();


        /*
        |--------------------------------------------------------------------------
        | Upload Documents
        |--------------------------------------------------------------------------
        */


        $frontimg = $request->file('frontimg');
        $backimg  = $request->file('backimg');


        $frontimgPath = $frontimg->store('uploads','public');
        $backimgPath  = $backimg->store('uploads','public');



        /*
        |--------------------------------------------------------------------------
        | Save KYC Application
        |--------------------------------------------------------------------------
        */


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


        $kyc->status = "Under review";
        $kyc->user_id = $user->id;


        $kyc->save();



        /*
        |--------------------------------------------------------------------------
        | Update User
        |--------------------------------------------------------------------------
        */


        $user->update([

            'kyc_id' => $kyc->id,

            'account_verify' => 'Under review',

            'dob' => $request->dob,

            'address' => $request->address

        ]);



        /*
        |--------------------------------------------------------------------------
        | Upload Profile Photo
        |--------------------------------------------------------------------------
        */


        if($request->hasFile('photo')){


            $photo = $request->file('photo');


            $photoName = $this->RandomStringGenerator(10)
                .time()
                .'.'
                .$photo->extension();


            $photoPath = $photo->storeAs(
                'public/photos',
                $photoName
            );


            $user->update([

                'profile_photo_path'=>$photoName

            ]);

        }




        /*
        |--------------------------------------------------------------------------
        | User Notification
        |--------------------------------------------------------------------------
        */


        NotificationHelper::create(

            $user,

            'Your KYC verification documents have been submitted successfully and are under review.',

            'KYC Verification Submitted',

            'info',

            'shield',

            '#'

        );




        /*
        |--------------------------------------------------------------------------
        | Admin Email Notification
        |--------------------------------------------------------------------------
        */


        $settings = Settings::find(1);



        if($settings){


            $message =
            "This is to inform you that {$user->name} submitted a KYC verification request. Login to admin dashboard to review.";


            Mail::to($settings->contact_email)
            ->send(
                new NewNotification(
                    $message,
                    "Identity Verification Request",
                    "Admin"
                )
            );


        }





        /*
        |--------------------------------------------------------------------------
        | API Response
        |--------------------------------------------------------------------------
        */


        return response()->json([

            'status'=>'success',

            'message'=>
            'Action Successful! Your KYC application is under review.'

        ],200);


    }





    /*
    |--------------------------------------------------------------------------
    | Random String Generator
    |--------------------------------------------------------------------------
    */


    function RandomStringGenerator($n)
    {

        $generated_string = "";

        $domain =
        "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";


        $len = strlen($domain);


        for($i=0;$i<$n;$i++)
        {

            $index = rand(0,$len-1);

            $generated_string .= $domain[$index];

        }


        return $generated_string;

    }


}