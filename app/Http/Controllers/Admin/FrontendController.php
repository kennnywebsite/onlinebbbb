<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Faq, Testimony, Images, Content, TermsPrivacy};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FrontendController extends Controller
{
    public function saveFaq(Request $request)
    {
        $faq = Faq::create([
            'ref_key'  => Str::random(6),
            'question' => $request->question,
            'answer'   => $request->answer,
        ]);
        return response()->json(['status' => 200, 'message' => 'FAQ Added!', 'data' => $faq]);
    }
  
    public function saveTestimony(Request $request)
    {
        $tes = Testimony::create([
            'name'         => $request->testifier,
            'ref_key'      => Str::random(6),
            'position'     => $request->position,
            'what_is_said' => $request->said,
            'picture'      => $request->picture,
        ]);
        return response()->json(['status' => 200, 'message' => 'Testimony Added!', 'data' => $tes]);
    }
  
    public function saveImg(Request $request)
    {
        $request->validate(['image' => 'required|image|mimes:jpg,jpeg,png']);
        $path = $request->file('image')->store('photos', 'public');
  
        $img = Images::create([
            'title'       => $request->img_title,
            'ref_key'     => Str::random(6),
            'description' => $request->img_desc,
            'img_path'    => $path,
        ]);
        return response()->json(['status' => 200, 'message' => 'Image Added!', 'data' => $img]);
    }
  
    public function updateImg(Request $request, $id)
    {
        $img = Images::findOrFail($id);
        $path = $img->img_path;

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($img->img_path);
            $path = $request->file('image')->store('photos', 'public');
        }

        $img->update([
            'title'       => $request->img_title,
            'description' => $request->img_desc,
            'img_path'    => $path,
        ]);
        return response()->json(['status' => 200, 'message' => 'Image Updated!']);
    }
  
    public function saveContents(Request $request)
    {
        $cont = Content::create([
            'title'       => $request->title,
            'ref_key'     => Str::random(6),
            'description' => $request->content,
        ]);
        return response()->json(['status' => 200, 'message' => 'Content Added!', 'data' => $cont]);
    }

    // --- Delete Methods ---
    public function delFaq($id) { Faq::destroy($id); return response()->json(['message' => 'Deleted']); }
    public function delTest($id) { Testimony::destroy($id); return response()->json(['message' => 'Deleted']); }

    /**
     * Privacy Policy Management
     */
    public function getTermsPolicy()
    {
        return response()->json(['data' => TermsPrivacy::find(1)]);
    }

    public function saveTermsPolicy(Request $request)
    {
        $terms = TermsPrivacy::find(1) ?? new TermsPrivacy();
        $terms->update([
            'description' => $request->termsprivacy,
            'useterms'    => $request->terms
        ]);
        return response()->json(['message' => 'Updated Successfully!']);
    }
}