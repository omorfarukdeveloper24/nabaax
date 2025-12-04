<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post_media;
use Illuminate\Support\Facades\Auth;

class PostMediaController extends Controller
{
    function __construct()
    {
        $this->middleware("auth.jwt", [
            
        ]);
    }

    
    public function list()
    {
        return response()->json(Post_media::with('post')->latest()->get());
    }

    
    public function store(Request $request)
    {
        $request->validate([
            'post_id'    => 'required|exists:posts,id',
            'media_type' => 'required|in:image,video,audio',
            'path'       => 'required|string',
            'meta'       => 'nullable|json',
            'order'      => 'nullable|integer',
        ]);

        $media = Post_media::create([
            'post_id'    => $request->post_id,
            'media_type' => $request->media_type,
            'path'       => $request->path,
            'meta'       => $request->meta,
            'order'      => $request->order ?? 0,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Post media created successfully!',
            'data'    => $media,
        ]);
    }

    
    public function details($id)
    {
        $media = Post_media::with('post')->find($id);

        if (!$media) {
            return response()->json(['status' => false, 'message' => 'Post media not found'], 404);
        }

        return response()->json($media);
    }

    
    public function update(Request $request, $id)
    {
        $media = Post_media::find($id);

        if (!$media) {
            return response()->json(['status' => false, 'message' => 'Post media not found'], 404);
        }

        $media->update($request->all());

        return response()->json([
            'status'  => true,
            'message' => 'Post media updated successfully!',
            'data'    => $media,
        ]);
    }

    
    public function destroy($id)
    {
        $media = Post_media::find($id);

        if (!$media) {
            return response()->json(['status' => false, 'message' => 'Post media not found'], 404);
        }

        $media->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Post media deleted successfully!',
        ]);
    }


}
