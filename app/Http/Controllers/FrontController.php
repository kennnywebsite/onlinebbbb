<?php

namespace App\Http\Controllers;

use App\Models\Images;
use App\Models\Content;
use App\Http\Controllers\Controller;

class FrontController extends Controller
{
    // Returns content as JSON
    public function getContent($ref_key, $prop){
        $content = Content::where('ref_key', $ref_key)->first();
        
        if (!$content) {
            return response()->json(['error' => 'Content not found'], 404);
        }

        return response()->json([
            'ref_key' => $ref_key,
            'property' => $prop,
            'value' => $content->$prop ?? null
        ]);
    }

    // Returns image data as JSON
    public function getImage($ref_key, $prop){
        $images = Images::where('ref_key', $ref_key)->first();
        
        if (!$images) {
            return response()->json(['error' => 'Image not found'], 404);
        }

        return response()->json([
            'ref_key' => $ref_key,
            'property' => $prop,
            'value' => $images->$prop ?? null
        ]);
    }
}