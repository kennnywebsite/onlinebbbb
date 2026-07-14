<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ipaddress;
use App\Models\Settings;

class IpaddressController extends Controller
{
    /**
     * Get blacklisted IPs as raw JSON
     */
    public function getAddresses()
    {
        // Return raw collection instead of HTML strings
        $addresses = Ipaddress::orderByDesc('id')->get();
        
        return response()->json([
            'status' => 200,
            'data' => $addresses,
            'message' => 'IPs retrieved successfully'
        ]);
    }

    /**
     * Add IP address
     */
    public function addIpAddress(Request $request)
    {
        $request->validate([
            'ipaddress' => 'required|ip|unique:ipaddresses,ipaddress'
        ]);

        $ip = Ipaddress::create([
            'ipaddress' => $request->ipaddress
        ]);

        return response()->json([
            'status' => 200, 
            'message' => "IP Address: {$request->ipaddress} blacklisted",
            'data' => $ip
        ]);
    }

    /**
     * Delete IP address
     */
    public function deleteIp($id)
    {
        $ip = Ipaddress::findOrFail($id);
        $ip->delete();
        
        return response()->json([
            'status' => 200, 
            'message' => 'IP Address deleted'
        ]);
    }
}