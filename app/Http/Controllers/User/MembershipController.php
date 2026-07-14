<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tp_Transaction;
use App\Models\User;
use App\Traits\PingServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MembershipController extends Controller
{
    use PingServer;

    public function courses()
    {
        // Assuming courses list comes from external API or local
        return response()->json(['title' => 'Courses', 'data' => []]);
    }

    public function courseDetails($course, $id)
    {
        $response = $this->fetctApi('/course', ['courseId' => $id]);
        $info = json_decode($response);

        return response()->json([
            'status' => 200,
            'data' => [
                'course' => $info->data->course ?? null,
                'lessons' => $info->data->lessons ?? []
            ]
        ]);
    }

    public function myCoursesDetails($id)
    {
        $response = $this->fetctApi('/user-course', [
            'courseId' => $id,
            'clientId' => Auth::id(),
        ]);

        $info = json_decode($response);

        return response()->json([
            'status' => 200,
            'data' => [
                'course' => $info->data ?? null,
                'lessons' => $info->data->lessons ?? []
            ]
        ]);
    }

    public function myCourses()
    {
        $response = $this->fetctApi('/user-courses', ['userId' => Auth::id()]);
        $info = json_decode($response);

        return response()->json([
            'status' => 200,
            'data' => $info->data->courses ?? []
        ]);
    }

    public function learning($lessonid, $courseid = null)
    {
        $info = json_decode($this->fetctApi('/course', [
            'userId' => Auth::id(),
            'courseId' => $courseid
        ]));

        $infoLesson = json_decode($this->fetctApi('/lesson', ['lessonId' => $lessonid]));

        return response()->json([
            'status' => 200,
            'data' => [
                'course' => $info->data->course ?? null,
                'lesson' => $infoLesson->data->lesson ?? null,
                'next'   => $infoLesson->data->nextlesson ?? null,
                'previous' => $infoLesson->data->previousLesson ?? null,
            ]
        ]);
    }

    public function buyCourse(Request $request)
    {
        $user = Auth::user();
        
        // 1. Fetch Course Info
        $courseInfo = json_decode($this->fetctApi('/course', ['courseId' => $request->course]));
        $course = $courseInfo->data->course ?? null;
        $amount = $course->amount ?? 0;

        // 2. Check if already purchased
        $userCourseInfo = json_decode($this->fetctApi('/user-course', [
            'courseId' => $request->course,
            'clientId' => $user->id
        ]));

        if (!empty($userCourseInfo->data)) {
            return response()->json(['status' => 400, 'message' => 'You have already purchased this course.'], 400);
        }

        // 3. Balance Check
        if ($user->account_bal < $amount) {
            return response()->json(['status' => 400, 'message' => 'Insufficient funds.'], 400);
        }

        // 4. Process Purchase
        $user->decrement('account_bal', $amount);

        $purchaseResponse = $this->fetctApi('/buy-course', [
            'courseId' => $request->course,
            'clientId' => $user->id
        ], 'POST');

        // 5. History
        Tp_Transaction::create([
            'user' => $user->id,
            'plan' => "Purchase Course",
            'amount' => $amount,
            'type' => "Education",
        ]);

        return response()->json([
            'status' => 200, 
            'message' => 'Course purchased successfully.'
        ]);
    }
}