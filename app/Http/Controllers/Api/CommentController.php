<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    function __construct()
    {
        $this->middleware("auth.jwt", [
            
        ]);
    }
    
    
    
    
    // public function list(Request $request)
    // {
    //     $member = Auth::guard('member')->user();
    //     if (!$member) {
    //         return response()->json([
    //             'status' => 'failed',
    //             'message' => 'Unauthorized user',
    //         ], 401);
    //     }
    
    //     $comments = Comment::where('post_id', $request->id)
    //         ->whereNull('parent_id')
    //         ->with(['member', 'replies'])
    //         ->latest()
    //         ->paginate(50);
    
    //     return response()->json([
    //         'status' => 'success',
    //         'data'   => $comments,
    //     ]);
    // }
    
    
    public function list(Request $request)
    {
        $member = Auth::guard('member')->user();
    
        if (!$member) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Unauthorized user',
            ], 401);
        }
    
        if (!Post::where('id', $request->id)->exists()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Post not found',
            ], 404);
        }
        
        
        
        
        $comments = Comment::select('id', 'post_id', 'member_id', 'parent_id', 'content', 'updated_at')
        ->where('post_id', $request->id)
        ->whereNull('parent_id')
        ->with([
            'member:id,name,username,image',
            'replies' => function ($query) {
                $query->select('id', 'post_id', 'member_id', 'parent_id', 'content', 'updated_at')
                      ->with('member:id,name,username,image');
            }
        ])
        ->latest()
        ->paginate(50);
        
        
        
        
    
        return response()->json([
            'status' => 'success',
            'data'   => $comments,
        ], 200);
    }
    
    
    // public function list()
    // {
    //     $comments = Comment::with(['post', 'member', 'parent'])->latest()->get();
    //     return response()->json($comments);
    // }

    public function store(Request $request)
    {
        // return $request;
        
        $member = Auth::guard('member')->user();
        if (!$member) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Unauthorized user'
            ], 401);
        }
    
        $validated = $request->validate([
            'post_id'   => 'required',
            'content'   => 'required',
            'parent_id' => 'nullable',
        ]);

        $validated['member_id'] = $member->id;
        
        // return $validated;
        
        $comment = Comment::create($validated);
        return response()->json([
            'status'  => 'success',
            'message' => 'Comment submitted successfully',
            'data'    => $comment,
        ], 200);
    }


    public function details($id)
    {
        $comment = Comment::with(['post', 'member', 'parent'])->findOrFail($id);
        return response()->json($comment);
    }

    public function update(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $comment->update($validated);

        return response()->json([
            'message' => 'কমেন্ট সফলভাবে আপডেট হয়েছে',
            'data' => $comment
        ]);
    }

    public function destroy($id)
    {
        $comment = Comment::findOrFail($id);
        $comment->delete();

        return response()->json([
            'message' => 'কমেন্ট ডিলিট করা হয়েছে'
        ]);
    }
    

}
