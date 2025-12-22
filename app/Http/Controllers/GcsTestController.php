<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GcsTestController extends Controller
{
    public function uploadImage(Request $request)
    {
      
        $request->validate([
            'photo' => 'required|image|max:2048', 
        ]);

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            
            $path = Storage::disk('gcs')->put('uploads', $file);

            $url = Storage::disk('gcs')->url($path);

            return "ছবিটি সফলভাবে আপলোড হয়েছে! আপনার ছবির লিঙ্ক: <a href='$url' target='_blank'>$url</a>";
        }

        return "কোনো ছবি পাওয়া যায়নি।";
    }
}