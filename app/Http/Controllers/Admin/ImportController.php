<?php

namespace App\Http\Controllers\Api\Admin;

use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Imports\UsersImport;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    /**
     * Import users from Excel/CSV file
     */
    public function fileImport(Request $request)
    {
        // 1. Validate file exists and is of correct type
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:5120', // 5MB max
        ]);

        try {
            // 2. Perform import
            Excel::import(new UsersImport, $request->file('file'));
            
            return response()->json([
                'status' => 200, 
                'message' => 'Leads successfully imported!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Import failed: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Return file URL or download stream for frontend
     */
    public function downloadDoc()
    {
        // Ensure file exists in storage
        $path = 'leads.xlsx';
        
        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Template file not found'], 404);
        }

        // Return the URL for the frontend to initiate the download
        return response()->json([
            'status' => 200,
            'download_url' => Storage::disk('public')->url($path)
        ]);
    }
}