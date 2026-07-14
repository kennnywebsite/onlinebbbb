<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Admin;
use App\Models\User;
use App\Mail\NewNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CrmController extends Controller
{
    public function addTask(Request $request)
    {
        $request->validate([
            'tasktitle'   => 'required|string',
            'delegation'  => 'required|exists:admins,id',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date',
            'priority'    => 'required|string',
        ]);

        $task = Task::create([
            'title'       => $request->tasktitle,
            'note'        => $request->note,
            'designation' => $request->delegation,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
            'priority'    => $request->priority,
            'status'      => 'Pending',
        ]);

        // Notify Admin
        $admin = Admin::findOrFail($request->delegation);
        $message = "A new task has been assigned: {$request->tasktitle}. Check your dashboard.";
        Mail::to($admin->email)->send(new NewNotification($message, "New Task: {$request->tasktitle}", $admin->firstName));

        return response()->json(['status' => 200, 'message' => 'Task successfully created and assigned.']);
    }

    public function updateTask(Request $request, $id)
    {
        Task::findOrFail($id)->update([
            'title'       => $request->tasktitle,
            'note'        => $request->note,
            'designation' => $request->delegation,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
            'priority'    => $request->priority,
        ]);

        return response()->json(['status' => 200, 'message' => 'Task updated successfully.']);
    }

    public function deleteTask($id)
    {
        Task::findOrFail($id)->delete();
        return response()->json(['status' => 200, 'message' => 'Task deleted.']);
    }

    public function markDone($id)
    {
        Task::findOrFail($id)->update(['status' => 'Completed']);
        return response()->json(['status' => 200, 'message' => 'Task marked as completed.']);
    }

    public function updateUser(Request $request)
    {
        User::findOrFail($request->id)->update(['userupdate' => $request->userupdate]);
        return response()->json(['status' => 200, 'message' => 'Status updated.']);
    }

    public function convert($id)
    {
        User::findOrFail($id)->update(['cstatus' => 'Customer']);
        return response()->json(['status' => 200, 'message' => 'User converted to customer.']);
    }

    public function assign(Request $request)
    {
        $request->validate(['user_name' => 'required', 'admin' => 'required']);
        
        User::findOrFail($request->user_name)->update(['assign_to' => $request->admin]);

        $admin = Admin::findOrFail($request->admin);
        $message = "A user has been assigned to you. Please login to your dashboard.";
        Mail::to($admin->email)->send(new NewNotification($message, "New User Assigned", $admin->firstName));

        return response()->json(['status' => 200, 'message' => 'User assigned successfully.']);
    }
}