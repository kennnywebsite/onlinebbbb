<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Plans;
use App\Models\User_plans;

class InvPlanController extends Controller
{
    /**
     * Add a new plan
     */
    public function addPlan(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric',
            // Add other validations as needed
        ]);

        $plan = Plans::create([
            'name'               => $request->name,
            'price'              => $request->price,
            'min_price'          => $request->min_price,
            'max_price'          => $request->max_price,
            'minr'               => $request->minr,
            'maxr'               => $request->maxr,
            'gift'               => $request->gift,
            'expected_return'    => $request->return,
            'increment_type'     => $request->t_type,
            'increment_interval' => $request->t_interval,
            'increment_amount'   => $request->t_amount,
            'expiration'         => $request->expiration,
            'type'               => 'Main',
        ]);

        return response()->json(['status' => 201, 'message' => 'Plan created successfully', 'data' => $plan]);
    }

    /**
     * Update an existing plan
     */
    public function updatePlan(Request $request, $id)
    {
        $plan = Plans::findOrFail($id);
        
        $plan->update([
            'name'               => $request->name,
            'price'              => $request->price,
            'min_price'          => $request->min_price,
            'max_price'          => $request->max_price,
            'minr'               => $request->minr,
            'maxr'               => $request->maxr,
            'gift'               => $request->gift,
            'expected_return'    => $request->return,
            'increment_type'     => $request->t_type,
            'increment_amount'   => $request->t_amount,
            'increment_interval' => $request->t_interval,
            'expiration'         => $request->expiration,
            'type'               => 'Main',
        ]);

        return response()->json(['status' => 200, 'message' => 'Plan updated successfully']);
    }

    /**
     * Delete a plan
     */
    public function trashPlan($id)
    {
        $plan = Plans::findOrFail($id);

        // Remove active plans from users
        User_plans::where('plan', $id)->delete();

        // Reset user's plan reference
        User::where('plan', $id)->update(['plan' => 0]);

        $plan->delete();

        return response()->json(['status' => 200, 'message' => 'Investment Plan deleted successfully!']);
    }
}