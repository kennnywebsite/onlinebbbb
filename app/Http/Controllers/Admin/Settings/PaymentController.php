<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Settings, SettingsCont, Wdmethod, Paystack, Cp_transaction};
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    /**
     * Get All Payment Configs
     */
    public function index()
    {
        return response()->json([
            'status' => 200,
            'data' => [
                'methods' => Wdmethod::orderByDesc('id')->get(),
                'coinpayments' => Cp_transaction::first(),
                'paystack' => Paystack::first(),
                'settings' => Settings::first(),
                'settings_cont' => SettingsCont::first(),
            ]
        ]);
    }

    /**
     * Store new payment method
     */
    public function addPayMethod(Request $request)
    {
        $request->validate(['barcode' => 'nullable|image|mimes:jpg,jpeg,png|max:500']);

        $data = $request->except('barcode');
        if ($request->hasFile('barcode')) {
            $data['barcode'] = $request->file('barcode')->store('photos', 'public');
        }

        Wdmethod::create($data);
        return response()->json(['status' => 200, 'message' => 'Payment Method Saved']);
    }

    /**
     * Update payment method
     */
    public function updateMethod(Request $request, $id)
    {
        $method = Wdmethod::findOrFail($id);
        $data = $request->except('barcode');

        if ($request->hasFile('barcode')) {
            Storage::disk('public')->delete($method->barcode);
            $data['barcode'] = $request->file('barcode')->store('photos', 'public');
        }

        $method->update($data);
        return response()->json(['status' => 200, 'message' => 'Payment Method Updated']);
    }

    public function deletePayMethod($id)
    {
        Wdmethod::findOrFail($id)->delete();
        return response()->json(['status' => 200, 'message' => 'Payment Method Deleted']);
    }

    /**
     * Update Payment Preferences
     */
    public function updatePreference(Request $request)
    {
        Settings::where('id', 1)->update($request->only([
            'withdrawal_option', 'deposit_option', 'auto_merchant_option', 
            'deduction_option', 'credit_card_provider'
        ]));

        SettingsCont::where('id', 1)->update(['minamt' => $request->minamt]);

        return response()->json(['status' => 200, 'message' => 'Settings saved successfully']);
    }

    /**
     * Update Gateways (Paystack/Flutterwave/Coinpayments)
     */
    public function updateGateway(Request $request)
    {
        Settings::where('id', 1)->update($request->only(['s_s_k', 's_p_k', 'pp_ci', 'pp_cs']));
        
        Paystack::where('id', 1)->update($request->only([
            'paystack_public_key', 'paystack_secret_key', 'paystack_url', 'paystack_email'
        ]));

        SettingsCont::where('id', 1)->update($request->only([
            'flw_public_key', 'flw_secret_key', 'flw_secret_hash', 'bnc_api_key', 'bnc_secret_key'
        ]));

        return response()->json(['status' => 200, 'message' => 'Gateway settings updated successfully']);
    }
}