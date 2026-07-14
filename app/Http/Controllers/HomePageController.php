<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{User, Settings, Plans, Faq, Testimony, Deposit, Withdrawal};
use App\Mail\NewNotification;
use Illuminate\Support\Facades\Mail;

class HomePageController extends Controller
{
    private function getCommonData() {
        $settings = Settings::where('id', '1')->first();
        return [
            'settings' => $settings,
            'total_users' => User::count(),
            'total_deposits' => Deposit::where('status', 'processed')->sum('amount'),
            'total_withdrawals' => Withdrawal::where('status', 'processed')->sum('amount'),
            'plans' => Plans::all(),
            'mplans' => Plans::where('type', 'Main')->get(),
            'pplans' => Plans::where('type', 'Promo')->get(),
            'faqs' => Faq::orderBy('id', 'desc')->get(),
            'test' => Testimony::orderBy('id', 'desc')->get(),
            'withdrawals' => Withdrawal::orderBy('id', 'DESC')->take(7)->get(),
            'deposits' => Deposit::orderBy('id', 'DESC')->take(7)->get(),
        ];
    }

    public function index() {
        return response()->json(array_merge($this->getCommonData(), ['title' => 'Home']));
    }

    public function investment() {
        return response()->json(array_merge($this->getCommonData(), ['title' => 'Pricing']));
    }

    public function statistics() {
        return response()->json(array_merge($this->getCommonData(), ['title' => 'Statistics']));
    }

    public function licensing() {
        return response()->json([
            'title' => 'Licensing',
            'settings' => Settings::where('id', '1')->first(),
            'mplans' => Plans::where('type', 'Main')->get(),
            'pplans' => Plans::where('type', 'Promo')->get(),
        ]);
    }

    public function tradebots() { return response()->json(['title' => 'Tradebots']); }
    public function margin() { return response()->json(['title' => 'Margin']); }
    public function business() { return response()->json(['title' => 'Business']); }
    public function personal() { 
        return response()->json(['title' => 'personal', 'settings' => Settings::where('id', '1')->first()]); 
    }
    public function cards() { 
        return response()->json(['title' => 'cards', 'settings' => Settings::where('id', '1')->first()]); 
    }
    public function grants() { 
        return response()->json(['title' => 'Grants', 'settings' => Settings::where('id', '1')->first()]); 
    }
    public function loans() { 
        return response()->json(['title' => 'loans', 'settings' => Settings::where('id', '1')->first()]); 
    }
    public function app() { 
        return response()->json(['title' => 'app', 'settings' => Settings::where('id', '1')->first()]); 
    }
    public function terms() { 
        return response()->json(['title' => 'Terms of Service', 'settings' => Settings::where('id', '1')->first()]); 
    }
    public function privacy() { 
        return response()->json(['title' => 'Privacy Policy', 'settings' => Settings::where('id', '1')->first()]); 
    }
    public function faq() { 
        return response()->json(['title' => 'FAQs', 'faqs' => Faq::orderBy('id', 'desc')->get(), 'settings' => Settings::where('id', '1')->first()]); 
    }
    public function about() { 
        return response()->json(['title' => 'About', 'settings' => Settings::where('id', '1')->first(), 'mplans' => Plans::where('type', 'Main')->get()]); 
    }
    public function contact() { 
        return response()->json(['title' => 'Contact', 'settings' => Settings::where('id', '1')->first()]); 
    }

    public function verify(Request $request) {
        $captcha = (string) rand(100000, 999999);
        $request->session()->put('code', $captcha);
        return response()->json(['captcha' => $captcha, 'title' => 'verify']);
    }

    public function codeverify(Request $request) {
        if ($request->session()->get('code') == $request->code) {
            return response()->json(['status' => 'success', 'message' => 'Verified']);
        }
        return response()->json(['status' => 'error', 'message' => 'Invalid Code'], 400);
    }

    public function sendcontact(Request $request) {
        $settings = Settings::where('id', '1')->first();
        $message = substr(wordwrap($request['message'], 70), 0, 350);
        $subject = "$request->subject, my email $request->email";

        Mail::to($settings->contact_email)->send(new NewNotification($message, $subject, 'Admin'));
        return response()->json(['status' => 'success', 'message' => 'Your message was sent successfully!']);
    }

    public function homesendcontact(Request $request) {
        $settings = Settings::where('id', '1')->first();
        $message = substr(wordwrap($request['message'], 70), 0, 350);
        $subject = "$request->subject, my email $request->email";

        Mail::to($settings->contact_email)->send(new NewNotification($message, $subject, 'Admin'));
        
        if (Mail::failures()) {
            return response()->json(['status' => 'error', 'message' => 'Message was not sent!'], 500);
        }
        return response()->json(['status' => 'success', 'message' => 'Your message was sent successfully!']);
    }
}