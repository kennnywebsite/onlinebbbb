<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class BeneficiaryController extends Controller
{
    /**
     * List all beneficiaries
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->get('type', 'all');
        $query = Auth::user()->beneficiaries();
        
        if ($type !== 'all') {
            $query->byType($type);
        }
        
        $beneficiaries = $query->orderBy('is_favorite', 'desc')
                              ->orderBy('usage_count', 'desc')
                              ->get();
        
        return response()->json(['success' => true, 'data' => $beneficiaries]);
    }

    /**
     * Store a newly created beneficiary
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->getRules($request));

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->except(['user_id']);
        $data['user_id'] = Auth::id();
        $beneficiary = Beneficiary::create($data);

        return response()->json([
            'success' => true, 
            'message' => 'Beneficiary saved successfully!', 
            'data' => $beneficiary
        ], 201);
    }

    /**
     * Update an existing beneficiary
     */
    public function update(Request $request, $id): JsonResponse
    {
        $beneficiary = Beneficiary::where('user_id', Auth::id())->findOrFail($id);
        
        $validator = Validator::make($request->all(), $this->getRules($request, $beneficiary->type, $beneficiary->method_type));

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $beneficiary->update($request->all());

        return response()->json(['success' => true, 'message' => 'Beneficiary updated successfully!', 'data' => $beneficiary]);
    }

    /**
     * Delete beneficiary
     */
    public function destroy($id): JsonResponse
    {
        $beneficiary = Beneficiary::where('user_id', Auth::id())->findOrFail($id);
        $beneficiary->delete();

        return response()->json(['success' => true, 'message' => 'Beneficiary deleted successfully!']);
    }

    /**
     * Toggle favorite status
     */
    public function toggleFavorite($id): JsonResponse
    {
        $beneficiary = Beneficiary::where('user_id', Auth::id())->findOrFail($id);
        $beneficiary->update(['is_favorite' => !$beneficiary->is_favorite]);
        
        return response()->json(['success' => true, 'is_favorite' => $beneficiary->is_favorite]);
    }

    /**
     * Validation Logic extracted to support Store & Update
     */
    /**
     * Helper to define validation rules for all methods
     */
    private function getRules(Request $request, $type = null, $methodType = null)
    {
        $type = $type ?? $request->type;
        $methodType = $methodType ?? $request->method_type;

        $rules = [
            'name' => 'required|string|max:255',
            'type' => 'required|in:local,international',
        ];

        if ($type === 'local') {
            $rules = array_merge($rules, [
                'account_name' => 'required|string|max:255',
                'account_number' => 'required|string|max:50',
                'bank_name' => 'required|string|max:255',
                'account_type' => 'required|string|max:100',
            ]);
        } elseif ($type === 'international') {
            $rules['method_type'] = 'required|string';
            
            switch ($methodType) {
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
        return $rules;
    }
}