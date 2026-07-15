<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class BeneficiaryController extends Controller
{
    /**
     * Display a listing of the user's beneficiaries
     */
    public function index(Request $request)
    {
        $type = $request->get('type', 'all'); // 'local', 'international', or 'all'
        
        $query = Auth::user()->beneficiaries();
        
        if ($type !== 'all') {
            $query->byType($type);
        }
        
        $beneficiaries = $query->orderBy('is_favorite', 'desc')
                              ->orderBy('usage_count', 'desc')
                              ->orderBy('last_used_at', 'desc')
                              ->get();
        
        return view('user.beneficiaries.index', [
            'title' => 'My Beneficiaries',
            'beneficiaries' => $beneficiaries,
            'type' => $type
        ]);
    }

    /**
     * Show the form for creating a new beneficiary
     */
    public function create(Request $request)
    {
        $type = $request->get('type', 'local');
        $method = $request->get('method', null);
        
        return view('user.beneficiaries.create', [
            'title' => 'Add New Beneficiary',
            'type' => $type,
            'method' => $method
        ]);
    }

    /**
     * Store a newly created beneficiary
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'type' => 'required|in:local,international',
        ];

        // Add validation rules based on type
        if ($request->type === 'local') {
            $rules = array_merge($rules, [
                'account_name' => 'required|string|max:255',
                'account_number' => 'required|string|max:50',
                'bank_name' => 'required|string|max:255',
                'account_type' => 'required|string|max:100',
                'routing_number' => 'nullable|string|max:9',
                'swift_code' => 'nullable|string|max:11',
            ]);
        } elseif ($request->type === 'international') {
            $rules['method_type'] = 'required|string';
            
            // Add specific validation based on method type
            switch ($request->method_type) {
                case 'Wire Transfer':
                    $rules = array_merge($rules, [
                        'account_name' => 'required|string|max:255',
                        'account_number' => 'required|string|max:50',
                        'bank_name' => 'required|string|max:255',
                        'bank_address' => 'required|string|max:500',
                        'account_type' => 'required|string|max:100',
                    ]);
                    break;
                case 'Cryptocurrency':
                    $rules = array_merge($rules, [
                        'crypto_currency' => 'required|string|max:10',
                        'crypto_network' => 'required|string|max:50',
                        'wallet_address' => 'required|string|max:255',
                    ]);
                    break;
                case 'PayPal':
                    $rules['paypal_email'] = 'required|email|max:255';
                    break;
                case 'Wise Transfer':
                    $rules['wise_email'] = 'required|email|max:255';
                    break;
                case 'Skrill':
                    $rules['skrill_email'] = 'required|email|max:255';
                    break;
                case 'Venmo':
                    $rules['venmo_username'] = 'required|string|max:100';
                    break;
                case 'Zelle':
                    $rules['zelle_email'] = 'required|email|max:255';
                    break;
                case 'Cash App':
                    $rules['cashapp_tag'] = 'required|string|max:100';
                    break;
                case 'Revolut':
                    $rules['revolut_email'] = 'required|email|max:255';
                    break;
                case 'Alipay':
                    $rules['alipay_id'] = 'required|string|max:100';
                    break;
                case 'WeChat Pay':
                    $rules['wechat_id'] = 'required|string|max:100';
                    break;
            }
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $beneficiaryData = $request->only([
            'name', 'type', 'account_name', 'account_number', 'bank_name', 'account_type',
            'bank_address', 'routing_number', 'swift_code', 'iban', 'crypto_currency', 'crypto_network',
            'wallet_address', 'paypal_email', 'wise_email', 'skrill_email', 'venmo_username',
            'venmo_phone', 'zelle_email', 'zelle_phone', 'cashapp_tag', 'revolut_email',
            'alipay_id', 'wechat_id', 'method_type'
        ]);

        $beneficiaryData['user_id'] = Auth::id();

        $beneficiary = Beneficiary::create($beneficiaryData);

        return response()->json([
            'success' => true,
            'message' => 'Beneficiary saved successfully!',
            'beneficiary' => [
                'id' => $beneficiary->id,
                'name' => $beneficiary->name,
                'initials' => $beneficiary->initials,
                'color' => $beneficiary->color,
                'primary_identifier' => $beneficiary->primary_identifier,
                'secondary_info' => $beneficiary->secondary_info,
                'is_favorite' => $beneficiary->is_favorite,
                'data' => $beneficiary->toArray()
            ]
        ]);
    }

    /**
     * Display the specified beneficiary
     */
    public function show(Beneficiary $beneficiary)
    {
        if ($beneficiary->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
        
        return view('user.beneficiaries.show', [
            'title' => 'Beneficiary Details',
            'beneficiary' => $beneficiary
        ]);
    }

    /**
     * Show the form for editing the specified beneficiary
     */
    public function edit(Beneficiary $beneficiary)
    {
        if ($beneficiary->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
        
        return view('user.beneficiaries.edit', [
            'title' => 'Edit Beneficiary',
            'beneficiary' => $beneficiary
        ]);
    }

    /**
     * Update the specified beneficiary
     */
    public function update(Request $request, Beneficiary $beneficiary)
    {
        if ($beneficiary->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
        
        $rules = [
            'name' => 'required|string|max:255',
        ];

        // Add validation rules based on type (similar to store method)
        if ($beneficiary->type === 'local') {
            $rules = array_merge($rules, [
                'account_name' => 'required|string|max:255',
                'account_number' => 'required|string|max:50',
                'bank_name' => 'required|string|max:255',
                'account_type' => 'required|string|max:100',
                'routing_number' => 'nullable|string|max:9',
                'swift_code' => 'nullable|string|max:11',
            ]);
        } elseif ($beneficiary->type === 'international') {
            // Similar validation as in store method
            switch ($beneficiary->method_type) {
                case 'Wire Transfer':
                    $rules = array_merge($rules, [
                        'account_name' => 'required|string|max:255',
                        'account_number' => 'required|string|max:50',
                        'bank_name' => 'required|string|max:255',
                        'bank_address' => 'required|string|max:500',
                        'country' => 'required|string|max:100',
                        'account_type' => 'required|string|max:100',
                    ]);
                    break;
                case 'Cryptocurrency':
                    $rules = array_merge($rules, [
                        'crypto_currency' => 'required|string|max:10',
                        'crypto_network' => 'required|string|max:50',
                        'wallet_address' => 'required|string|max:255',
                    ]);
                    break;
                case 'PayPal':
                    $rules['paypal_email'] = 'required|email|max:255';
                    break;
                case 'Wise Transfer':
                    $rules['wise_email'] = 'required|email|max:255';
                    break;
                case 'Skrill':
                    $rules['skrill_email'] = 'required|email|max:255';
                    break;
                case 'Venmo':
                    $rules['venmo_username'] = 'required|string|max:100';
                    break;
                case 'Zelle':
                    $rules['zelle_email'] = 'required|email|max:255';
                    break;
                case 'Cash App':
                    $rules['cashapp_tag'] = 'required|string|max:100';
                    break;
                case 'Revolut':
                    $rules['revolut_email'] = 'required|email|max:255';
                    break;
                case 'Alipay':
                    $rules['alipay_id'] = 'required|string|max:100';
                    break;
                case 'WeChat Pay':
                    $rules['wechat_id'] = 'required|string|max:100';
                    break;
            }
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('message', 'Please check the form for errors.');
        }

        $beneficiaryData = $request->only([
            'name', 'account_name', 'account_number', 'bank_name', 'account_type',
            'bank_address', 'routing_number', 'country', 'swift_code', 'iban', 'crypto_currency', 'crypto_network',
            'wallet_address', 'paypal_email', 'wise_email', 'skrill_email', 'venmo_username',
            'venmo_phone', 'zelle_email', 'zelle_phone', 'cashapp_tag', 'revolut_email',
            'alipay_id', 'wechat_id'
        ]);

        $beneficiary->update($beneficiaryData);

        return redirect()->route('beneficiaries.index', ['type' => $beneficiary->type])
            ->with('success', 'Beneficiary updated successfully!');
    }

    /**
     * Remove the specified beneficiary
     */
    public function destroy(Beneficiary $beneficiary)
    {
        if ($beneficiary->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $beneficiary->delete();

        return response()->json([
            'success' => true,
            'message' => 'Beneficiary deleted successfully!'
        ]);
    }

    /**
     * Toggle favorite status of beneficiary
     */
    public function toggleFavorite(Beneficiary $beneficiary): JsonResponse
    {
        if ($beneficiary->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $beneficiary->update(['is_favorite' => !$beneficiary->is_favorite]);
        
        return response()->json([
            'success' => true,
            'is_favorite' => $beneficiary->is_favorite,
            'message' => $beneficiary->is_favorite ? 'Added to favorites' : 'Removed from favorites'
        ]);
    }

    /**
     * Get beneficiaries for AJAX requests (used in transfer forms)
     */
    public function getBeneficiaries(Request $request): JsonResponse
    {
        $type = $request->get('type', null);
        $method = $request->get('method', null);
        $favorites = $request->get('favorites', false);
        
        $query = Auth::user()->beneficiaries();
        
        // Filter by type only if specified
        if ($type && $type !== 'all') {
            $query->byType($type);
        }
        
        // Filter by favorites if requested
        if ($favorites === 'true' || $favorites === true) {
            $query->where('is_favorite', true);
        }
        
        // Filter by method for international transfers
        if ($method && ($type === 'international' || !$type)) {
            $query->where('method_type', $method);
        }
        
        $beneficiaries = $query->orderBy('is_favorite', 'desc')
                              ->orderBy('usage_count', 'desc')
                              ->orderBy('last_used_at', 'desc')
                              ->limit(10)
                              ->get();
        
        return response()->json([
            'success' => true,
            'beneficiaries' => $beneficiaries->map(function ($beneficiary) {
                return [
                    'id' => $beneficiary->id,
                    'name' => $beneficiary->name,
                    'type' => $beneficiary->type,
                    'method_type' => $beneficiary->method_type,
                    'initials' => $beneficiary->initials,
                    'color' => $beneficiary->color,
                    'primary_identifier' => $beneficiary->primary_identifier,
                    'secondary_info' => $beneficiary->secondary_info,
                    'is_favorite' => $beneficiary->is_favorite,
                    'usage_count' => $beneficiary->usage_count,
                    'last_used_at' => $beneficiary->last_used_at,
                    'account_name' => $beneficiary->account_name,
                    'account_number' => $beneficiary->account_number,
                    'bank_name' => $beneficiary->bank_name,
                    'account_type' => $beneficiary->account_type,
                    'bank_address' => $beneficiary->bank_address,
                    'country' => $beneficiary->country,
                    'swift_code' => $beneficiary->swift_code,
                    'iban' => $beneficiary->iban,
                    'crypto_currency' => $beneficiary->crypto_currency,
                    'crypto_network' => $beneficiary->crypto_network,
                    'wallet_address' => $beneficiary->wallet_address,
                    'paypal_email' => $beneficiary->paypal_email,
                    'wise_email' => $beneficiary->wise_email,
                    'skrill_email' => $beneficiary->skrill_email,
                    'venmo_username' => $beneficiary->venmo_username,
                    'venmo_phone' => $beneficiary->venmo_phone,
                    'zelle_email' => $beneficiary->zelle_email,
                    'zelle_phone' => $beneficiary->zelle_phone,
                    'cashapp_tag' => $beneficiary->cashapp_tag,
                    'revolut_email' => $beneficiary->revolut_email,
                    'alipay_id' => $beneficiary->alipay_id,
                    'wechat_id' => $beneficiary->wechat_id,
                    'data' => $beneficiary->toArray()
                ];
            })
        ]);
    }

    /**
     * Get a specific beneficiary's data for auto-filling forms
     */
    public function getBeneficiaryData(Beneficiary $beneficiary): JsonResponse
    {
        if ($beneficiary->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
        
        // Increment usage count
        $beneficiary->incrementUsage();
        
        return response()->json([
            'success' => true,
            'beneficiary' => $beneficiary->toArray()
        ]);
    }
} 