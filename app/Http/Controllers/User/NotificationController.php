<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get list of notifications.
     */
    public function index()
    {
        $notifications = Auth::user()->notifications()->latest()->paginate(15);
        
        return response()->json([
            'status' => 200,
            'data' => $notifications
        ]);
    }

    /**
     * Mark single notification as read.
     */
    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);
        
        if ((int) $notification->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $notification->update(['is_read' => true]);
        
        return response()->json([
            'status' => 200,
            'message' => 'Notification marked as read',
            'link' => $notification->link // API consumer can redirect if this exists
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead()
    {
        Auth::user()->notifications()->update(['is_read' => true]);
        
        return response()->json([
            'status' => 200, 
            'message' => 'All notifications marked as read.'
        ]);
    }

    /**
     * Delete a single notification.
     */
    public function destroy($id)
    {
        $notification = Notification::findOrFail($id);
        
        if ((int) $notification->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $notification->delete();
        
        return response()->json([
            'status' => 200, 
            'message' => 'Notification deleted successfully.'
        ]);
    }

    /**
     * Delete all notifications.
     */
    public function destroyAll()
    {
        Auth::user()->notifications()->delete();
        
        return response()->json([
            'status' => 200, 
            'message' => 'All notifications deleted successfully.'
        ]);
    }
}