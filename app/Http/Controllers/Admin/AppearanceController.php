<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AppearanceSettings;

class AppearanceController extends Controller
{
    /**
     * Get appearance settings for the dashboard UI
     */
    public function index()
    {
        return response()->json([
            'status' => 200,
            'data' => AppearanceSettings::getSettings()
        ]);
    }

    /**
     * Update appearance settings
     */
    public function update(Request $request)
    {
        try {
            $settings = AppearanceSettings::getSettings();
            
            // Only update fields that exist in the request
            $data = $request->all();
            
            // Explicitly handle boolean flags
            $data['use_gradient'] = $request->boolean('use_gradient');
            $data['disable_animations'] = $request->boolean('disable_animations');
            
            $settings->update($data);
            
            return response()->json([
                'status' => 200, 
                'message' => 'Appearance settings updated successfully.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset appearance settings to default values
     */
    public function reset()
    {
        $settings = AppearanceSettings::getSettings();
        
        $defaults = [
            'primary_color' => '#0ea5e9',
            'primary_color_foreground' => '#ffffff',
            'secondary_color' => '#64748b',
            'accent_color' => '#ec4899',
            'background_color' => '#f8fafc',
            'foreground_color' => '#1e293b',
            'use_gradient' => true,
            'disable_animations' => false,
            // ... (Include other default fields as needed)
        ];
        
        $settings->update($defaults);
        
        return response()->json([
            'status' => 200, 
            'message' => 'Appearance settings reset to defaults.',
            'data' => $settings
        ]);
    }
}