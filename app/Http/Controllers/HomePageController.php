<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Settings;
use App\Models\Plans;
use App\Models\Faq;
use App\Models\Testimony;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Models\TermsPrivacy;
use Illuminate\Support\Facades\DB;
use App\Mail\NewNotification;
use Illuminate\Support\Facades\Mail;

class HomePageController extends Controller
{
    public function index(){
        $settings=Settings::where('id', '=', '1')->first();
        //sum total deposited
        $total_deposits = Deposit::where('status','processed')->sum('amount');
        
        
        
        //sum total withdrawals
        $total_withdrawals = Withdrawal::where('status','processed')->sum('amount');
        
        
        
      
        return view('home.index')->with(array(
            'settings' => $settings,
            'total_users' => User::count(),
            'plans' => Plans::all(),
            'total_deposits' => $total_deposits,
            'total_withdrawals' => $total_withdrawals,
            'faqs'=> Faq::orderby('id', 'desc')->get(),
            'test'=> Testimony::orderby('id', 'desc')->get(),
            'withdrawals' => Withdrawal::orderby('id','DESC')->take(7)->get(),
            'deposits' => Deposit::orderby('id','DESC')->take(7)->get(),
            'title' => $settings->site_title,
            'mplans' => Plans::where('type','Main')->get(),
            'pplans' => Plans::where('type','Promo')->get(),
        ));
    }


public function investment(){
        $settings=Settings::where('id', '=', '1')->first();
        //sum total deposited
        $total_deposits = Deposit::where('status','processed')->sum('amount');
        
        
        
        //sum total withdrawals
        $total_withdrawals = Withdrawal::where('status','processed')->sum('amount');
        
        
        
      
        return view('home.pricing')->with(array(
            'settings' => $settings,
            'total_users' => User::count(),
            'plans' => Plans::all(),
            'total_deposits' => $total_deposits,
            'total_withdrawals' => $total_withdrawals,
            'faqs'=> Faq::orderby('id', 'desc')->get(),
            'test'=> Testimony::orderby('id', 'desc')->get(),
            'withdrawals' => Withdrawal::orderby('id','DESC')->take(7)->get(),
            'deposits' => Deposit::orderby('id','DESC')->take(7)->get(),
            'title' => $settings->site_title,
            'mplans' => Plans::where('type','Main')->get(),
            'pplans' => Plans::where('type','Promo')->get(),
        ));
    }


public function statistics(){
        $settings=Settings::where('id', '=', '1')->first();
        //sum total deposited
        $total_deposits = Deposit::where('status','processed')->sum('amount');
        
        
        
        //sum total withdrawals
        $total_withdrawals = Withdrawal::where('status','processed')->sum('amount');
        
        
        
      
        return view('home.statistics')->with(array(
            'settings' => $settings,
            'total_users' => User::count(),
            'plans' => Plans::all(),
            'total_deposits' => $total_deposits,
            'total_withdrawals' => $total_withdrawals,
            'faqs'=> Faq::orderby('id', 'desc')->get(),
            'test'=> Testimony::orderby('id', 'desc')->get(),
            'withdrawals' => Withdrawal::orderby('id','DESC')->take(7)->get(),
            'deposits' => Deposit::orderby('id','DESC')->take(7)->get(),
            'title' => $settings->site_title,
            'mplans' => Plans::where('type','Main')->get(),
            'pplans' => Plans::where('type','Promo')->get(),
        ));
    }



    //Licensing and registration route
    public function licensing(){
        
        return view('home.licensing')
        ->with(array(
            'mplans' => Plans::where('type','Main')->get(),
            'pplans' => Plans::where('type','Promo')->get(),
            'title' => 'Licensing, regulation and registration',
            'settings' => Settings::where('id', '=', '1')->first(),
        ));
    }
//tradebots
public function tradebots(){
        
    return view('home.tradebots');
    
}
    ////margin
    public function margin(){
        
        return view('home.margin');
        
    }

public function business(){
       
    return view('home.business');
    
}


public function personal(){
       
    return view('home.personal')->with(array(
        'title' => 'personal',
        'settings' => Settings::where('id', '=', '1')->first(),
));
    
}



public function cards(){
       
    return view('home.cards')->with(array(
        'title' => 'cards',
        'settings' => Settings::where('id', '=', '1')->first(),
));
    
}

public function grants(){
       
    return view('home.grants')->with(array(
        'title' => 'Grants',
        'settings' => Settings::where('id', '=', '1')->first(),
));
    
}


public function loans(){
       
    return view('home.loans')->with(array(
        'title' => 'loans',
        'settings' => Settings::where('id', '=', '1')->first(),
));
    
}

public function app(){
       
    return view('home.app')->with(array(
        'title' => 'app',
        'settings' => Settings::where('id', '=', '1')->first(),
));
    
}


    //Terms of service route
    public function terms(){
        
        return view('home.terms')
        ->with(array(
            'title' => 'Terms of Service',
            'settings' => Settings::where('id', '=', '1')->first(),
        ));
    }

    //Privacy policy route
    public function privacy(){
        
        return view('home.privacy')
        ->with(array(
            'title' => 'Privacy Policy',
            'settings' => Settings::where('id', '=', '1')->first(),
        ));
    }

    //FAQ route
    public function faq(){
        
        return view('home.faq')
        ->with(array(
            'title' => 'FAQs',
            'faqs'=> Faq::orderby('id', 'desc')->get(),
            'settings' => Settings::where('id', '=', '1')->first(),
        ));
    }

    //about route
    public function about(){
        
        return view('home.about')
        ->with(array(
            'mplans' => Plans::where('type','Main')->get(),
                
            'title' => 'About',
            'settings' => Settings::where('id', '=', '1')->first(),
        ));
    }

    //Contact route
    public function contact(){
        return view('home.contact')
        ->with(array(
            'mplans' => Plans::where('type','Main')->get(),
                'pplans' => Plans::where('type','Promo')->get(),
                
            'title' => 'Contact',
            'settings' => Settings::where('id', '=', '1')->first(),
        ));
    }

  
 public function verify(Request $request){
        
$n1 = rand(0,9);
$n2 = rand(0,9);
$n3 = rand(0,9);
$n4 = rand(0,9);
$n5 = rand(0,9);
$n6 = rand(0,9);

$captcha = "$n1$n2$n3$n4$n5$n6";
  $request->session()->put('code',$captcha);

        return view('home.verify')
        ->with(array(
          
            'captcha'=> $captcha,
            'title' => 'verify',
            'settings' => Settings::where('id', '=', '1')->first(),
        ));
    }
    
    
    
     public function codeverify(Request $request){
      $code =  $request->session()->get('code');
      if($code == $request->code){
          
           return redirect()->route('register');
      }
       
       
       return redirect()->back()->with('success', 'Invalid Code Supplied');

    }
    //send contact message to admin email
    public function sendcontact(Request $request){

        $settings=Settings::where('id','1')->first();
        $message = substr(wordwrap($request['message'],70),0,350);
        $subject = "$request->subject, my email $request->email";

        Mail::to($settings->contact_email)->send(new NewNotification($message, $subject, 'Admin'));
        return redirect()->back()
        ->with('success', ' Your message was sent successfully!');
    }


    public function homesendcontact(Request $request){

        $settings=Settings::where('id','1')->first();
        $message = substr(wordwrap($request['message'],70),0,350);
        $subject = "$request->subject, my email $request->email";

        Mail::to($settings->contact_email)->send(new NewNotification($message, $subject, 'Admin'));
        
         if (Mail::failures()) {
         return redirect()->back()
        ->with('message', ' message was not sent! Please try again later');
    }
        return redirect()->back()
        ->with('success', ' Your message was sent successfully!');
    }
}
