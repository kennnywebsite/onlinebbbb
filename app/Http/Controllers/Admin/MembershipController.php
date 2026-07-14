<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Traits\PingServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MembershipController extends Controller
{
    use PingServer;

    public function showCourses(Request $request)
    {
        $response = $this->fetctApi('/courses', ['value' => $request->searchValue]);
        $info = json_decode($response);

        return response()->json([
            'status' => 200,
            'data' => [
                'courses' => $info->data->courses ?? [],
                'categories' => $info->data->categories ?? []
            ]
        ]);
    }

    public function addCourse(Request $request)
    {
        $path = $this->handleFileUpload($request);
        if (!$path) return response()->json(['status' => 'error', 'message' => 'Image required'], 400);

        $response = $this->fetctApi('/add-course', [
            'title' => $request->title,
            'amount' => $request->amount,
            'image_url' => $path,
            'paidCourses' => !empty($request->amount),
            'category' => $request->category,
            'desc' => $request->desc
        ], 'POST');

        return $this->apiResponse($response);
    }

    public function updateCourse(Request $request)
    {
        $path = $this->handleFileUpload($request);
        if (!$path) return response()->json(['status' => 'error', 'message' => 'Image required'], 400);

        $response = $this->fetctApi('/update-course', [
            'course_id' => $request->course_id,
            'title' => $request->title,
            'amount' => $request->amount,
            'image_url' => $path,
            'paidCourses' => !empty($request->amount),
            'category' => $request->category,
            'desc' => $request->desc
        ], 'POST');

        return $this->apiResponse($response);
    }

    public function deleteCourse($courseId)
    {
        $res = $this->fetctApi('/course', ['courseId' => $courseId]);
        $info = json_decode($res);
        
        if (isset($info->data->course->id)) {
            Storage::disk('public')->delete($info->data->course->id);
        }

        $response = $this->fetctApi("/delete-course/$courseId", [], 'DEL');
        return $this->apiResponse($response);
    }

    public function showLessons($id)
    {
        $response = $this->fetctApi("/courses-lessons/$id");
        $info = json_decode($response);
        
        return response()->json([
            'status' => 200,
            'data' => [
                'lessons' => $info->data->lessons->data ?? [],
                'course' => $info->data->course ?? null
            ]
        ]);
    }

    public function addLesson(Request $request)
    {
        $path = $this->handleFileUpload($request);
        if (!$path) return response()->json(['status' => 'error', 'message' => 'Thumbnail required'], 400);

        $response = $this->fetctApi('/add-lesson', [
            'title' => $request->title,
            'length' => $request->length,
            'videolink' => $request->videolink,
            'preview' => $request->preview,
            'course_id' => $request->course_id,
            'desc' => $request->desc,
            'cat' => $request->category,
            'thumbnail' => $path
        ], 'POST');

        return $this->apiResponse($response);
    }

    public function deleteLesson($lessonId)
    {
        $res = $this->fetctApi('/lesson', ['lessonId' => $lessonId]);
        $info = json_decode($res);

        if (isset($info->data->lesson->id)) {
            Storage::disk('public')->delete($info->data->lesson->id);
        }

        $response = $this->fetctApi("/delete-lesson/$lessonId", [], 'DEL');
        return $this->apiResponse($response);
    }

    // Helper: Centralize File Handling
    private function handleFileUpload(Request $request)
    {
        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpg,jpeg,png|max:1000']);
            return $request->file('image')->store('uploads', 'public');
        }
        return $request->image_url;
    }

    // Helper: Standardized JSON Response
    private function apiResponse($response)
    {
        if ($response->failed()) {
            return response()->json(['status' => 'error', 'message' => $response['message'] ?? 'Action failed'], 400);
        }
        return response()->json(['status' => 'success', 'message' => $response['message'] ?? 'Action successful']);
    }
}