<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AppearanceSettings;
use App\Models\Settings;

class AppearanceController extends Controller
{
    /**
     * Display the appearance settings page.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $appearanceSettings = AppearanceSettings::getSettings();
        return view('admin.appearance.index', [
            'title' => 'Appearance Settings',
            'appearanceSettings' => $appearanceSettings,
            'settings' => Settings::where('id', '1')->first(),
        ]);
    }

    /**
     * Update the appearance settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        try {
            $settings = AppearanceSettings::getSettings();
            
            // Get all request data except token
            $data = $request->except(['_token']);
            
            // Handle boolean fields
            $data['use_gradient'] = $request->has('use_gradient');
            $data['disable_animations'] = $request->has('disable_animations');
            
            // Update all fields
            $settings->fill($data);
            $settings->save();
            
            return redirect()->route('admin.appearance.index')->with('success', 'Appearance settings updated successfully.');
            
        } catch (\Exception $e) {
            return redirect()->route('admin.appearance.index')->with('message', 'An error occurred: ' . $e->getMessage())->with('type', 'error');
        }
    }

    /**
     * Reset appearance settings to default values.
     *
     * @return \Illuminate\Http\Response
     */
    public function reset()
    {
        $settings = AppearanceSettings::getSettings();
        
        // Create a new instance with default values
        $defaultSettings = new AppearanceSettings([
            // Primary color variants (sky blue)
            'primary_color' => '#0ea5e9', // sky-500
            'primary_color_50' => '#f0f9ff',
            'primary_color_100' => '#e0f2fe',
            'primary_color_200' => '#bae6fd',
            'primary_color_300' => '#7dd3fc',
            'primary_color_400' => '#38bdf8',
            'primary_color_600' => '#0284c7',
            'primary_color_700' => '#0369a1',
            'primary_color_foreground' => '#ffffff',
            
            // Secondary color variants (slate)
            'secondary_color' => '#64748b', // slate-500
            'secondary_color_50' => '#f8fafc',
            'secondary_color_100' => '#f1f5f9',
            'secondary_color_200' => '#e2e8f0',
            'secondary_color_300' => '#cbd5e1',
            'secondary_color_400' => '#94a3b8',
            'secondary_color_600' => '#475569',
            'secondary_color_700' => '#334155',
            'secondary_color_foreground' => '#0f172a',
            
            // Accent color variants (pink)
            'accent_color' => '#ec4899',
            'accent_color_50' => '#fdf2f8',
            'accent_color_100' => '#fce7f3',
            'accent_color_200' => '#fbcfe8',
            'accent_color_300' => '#f9a8d4',
            'accent_color_400' => '#f472b6',
            'accent_color_600' => '#db2777',
            'accent_color_700' => '#be185d',
            'accent_color_foreground' => '#ffffff',
            
            // Background, foreground, and other UI colors
            'background_color' => '#f8fafc',
            'foreground_color' => '#1e293b',
            'card_color' => '#ffffff',
            'card_foreground_color' => '#1e293b',
            'muted_color' => '#f1f5f9',
            'muted_foreground_color' => '#64748b',
            'border_color' => '#e2e8f0',
            'input_color' => '#e2e8f0',
            'ring_color' => '#0ea5e9',
            
            // Gradient colors
            'gradient_pink_from' => '#ec4899',
            'gradient_purple_via' => '#a855f7',
            'gradient_indigo_to' => '#4f46e5',
            
            // Action colors
            'yellow_action' => '#facc15',
            'green_positive' => '#22c55e',
            'red_negative' => '#ef4444',
            
            // Preloader specific colors
            'preloader_background' => 'radial-gradient(ellipse at center, rgba(14, 165, 233, 0.08) 0%, rgba(255, 255, 255, 0.95) 50%, rgba(255, 255, 255, 1) 100%)',
            'preloader_background_dark' => 'radial-gradient(ellipse at center, rgba(14, 165, 233, 0.12) 0%, rgba(17, 24, 39, 0.95) 50%, rgba(17, 24, 39, 1) 100%)',
            'preloader_text_color' => '#1e293b',
            'preloader_accent_color' => '#0ea5e9',
            
            // Other settings
            'use_gradient' => true,
            'gradient_direction' => 'to right',
            'custom_css' => null,
            'disable_animations' => false,
            'notes' => 'Default appearance settings'
        ]);
        
        // Update the existing settings with default values
        $settings->fill($defaultSettings->getAttributes());
        $settings->save();
        
        return redirect()->route('admin.appearance.index')->with('success', 'Appearance settings reset to defaults.');
    }
}