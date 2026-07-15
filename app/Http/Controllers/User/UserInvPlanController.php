<?php

namespace App\Http\Controllers\User;



use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Settings;
use App\Models\Plans;
use App\Models\Tp_Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Mail\NewNotification;
use App\Models\User_plans;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class UserInvPlanController extends Controller
{



    public function loan(Request $request){
        //get user
        $user=User::where('id',Auth::user()->id)->first();
        //get plan
    
    
        //save user laon
        $userplanid = DB::table('user_plans')->insertGetId([
            
            'user' => Auth::user()->id,
            'amount' => $plan_price,
            'income'=> $request['income'],
            'purpose'=> $request['purpose'],
            'duration'=>$request['duration'],
            'facility' => $request['facility'],
            'active' => 'Pending',
            'inv_duration'=>$request['duration'],
            'expire_date' => $end_at,
            'activated_at' => \Carbon\Carbon::now(),
            'last_growth' => \Carbon\Carbon::now(),
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now(),
        ]);

        User::where('id',Auth::user()->id)
        ->update([
          
          'user_plan' => $userplanid,
          'entered_at'=>\Carbon\Carbon::now(),
        ]);

        //send notification
        $settings=Settings::where('id', '=', '1')->first();
        $message = "This is to inform you that $user->name just applied for a loan plan for $request->purpose";
        $subject ="Loan Application by $user->name ";
        Mail::to($settings->contact_email)->send(new NewNotification($message, $subject, 'Admin'));

        return redirect()->back()
          ->with('success', "You have successfully applied for a loan your laon is currently pending, you will be contacted soon.");
    }

    public function cancelPlan($plan)
    {
        $plan = User_plans::find($plan);
        $plan->active = 'cancelled';
        $plan->save();

        // credit the user his capital
        User::where('id', $plan->user)
            ->update([
                'account_bal' => Auth::user()->account_bal + $plan->amount,
            ]);

        //save to transactions history
        $th = new Tp_transaction();
        $th->plan = $plan->dplan->name;
        $th->user = $plan->user;
        $th->amount = $plan->amount;
        $th->type = "Investment capital for cancelled plan";
        $th->save();

        // Send a mail to the user informing them of their plan cancellation
        $planName = $plan->dplan->name;
        $message = "You have succesfully cancelled your $planName plan and your investment capital have been credited to your account,  If this is a mistake, please contact us immediately to reactivate it for you.";
        Mail::to(Auth::user()->email)->send(new NewNotification($message, 'Invsetment Plan Cancelled', Auth::user()->name));

        return back()->with('success', 'Plan cancelled successfully');
    }



      //Apply for loan
    
}