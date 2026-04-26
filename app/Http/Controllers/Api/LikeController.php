<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Like;
use Illuminate\Support\Facades\Auth;

class LikeController extends Controller
{
    function __construct()
    {
        $this->middleware("auth.jwt", [
            
        ]);
    }
    
    
    public function list(Request $request)
    {
         $member = Auth::guard('member')->user();
        if (!$member) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Unauthorized user'
            ], 401);
        }
        $likes = Like::where('post_id',$request->id)->with(['post', 'member'])->latest()->get();
        $like_count = $likes->count();
        return response()->json(['status'=>'success','like'=>$like_count]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'post_id' => 'required',
            'type' => 'required',
        ]);
        // return $request;
        
        
         
        $member = Auth::guard("member")->user();
        
       if (!$member) {
        return response()->json([
            'status' => failed,
                'message' => 'Unauthorized user'
            ], 401);
        }

       
        $like = Like::updateOrCreate(
            [
                'post_id' => $validated['post_id'],
                'member_id' => $member->id,
            ],
            [
                'type' => $validated['type'],
            ]
        );

        return response()->json([
            'status'=>'success',
            'message' => 'Like submit successfully',
            'data' => $like
        ]);
    }

    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'post_id' => 'required|exists:posts,id',
    //         'type' => 'required|in:1,2', // ১ = লাইক, ২ = ডিসলাইক
    //     ]);

    //     $member = Auth::guard("member")->user();
    //     if (!$member) {
    //         return response()->json(['status' => 'failed', 'message' => 'Unauthorized'], 401);
    //     }

    //     $postId = $validated['post_id'];
    //     $newType = (int)$validated['type'];
    //     $memberId = $member->id;

    //     // ১. আগের কোনো রিঅ্যাকশন আছে কিনা চেক করুন
    //     $existingLike = Like::where('post_id', $postId)->where('member_id', $memberId)->first();

    //     // ডাটাবেস ট্রানজ্যাকশন ব্যবহার করা নিরাপদ যাতে কাউন্টিং ভুল না হয়
    //     $like = DB::transaction(function () use ($postId, $memberId, $newType, $existingLike) {
    //         $post = Post::lockForUpdate()->find($postId);

    //         if ($existingLike) {
    //             if ($existingLike->type == $newType) {
    //                 // একই বাটনে আবার চাপ দিলে (Toggle off/Remove)
    //                 $existingLike->delete();
    //                 $this->updatePostCount($post, $newType, -1);
    //                 return null; 
    //             } else {
    //                 // লাইক থেকে ডিসলাইক বা ডিসলাইক থেকে লাইক (Switch)
    //                 $oldType = $existingLike->type;
    //                 $existingLike->update(['type' => $newType]);
    //                 $this->updatePostCount($post, $oldType, -1); // আগেরটা কমান
    //                 $this->updatePostCount($post, $newType, 1);  // নতুনটা বাড়ান
    //                 return $existingLike;
    //             }
    //         } else {
    //             // একদম নতুন লাইক/ডিসলাইক
    //             $newLike = Like::create([
    //                 'post_id' => $postId,
    //                 'member_id' => $memberId,
    //                 'type' => $newType
    //             ]);
    //             $this->updatePostCount($post, $newType, 1);
    //             return $newLike;
    //         }
    //     });

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => $like ? 'Reaction saved' : 'Reaction removed',
    //         'data' => $like
    //     ]);
    // }

    // // কাউন্ট আপডেট করার জন্য একটি প্রাইভেট ফাংশন
    // private function updatePostCount($post, $type, $value)
    // {
    //     if ($type == 1) {
    //         $post->increment('like_count', $value);
    //     } else {
    //         $post->increment('dislike_count', $value);
    //     }
    // }


    public function details($id)
    {
        $like = Like::with(['post', 'member'])->findOrFail($id);
        return response()->json($like);
    }

    

    public function update(Request $request)
    {
        $validated = $request->validate([
            'post_id' => 'required',
            'type'    => 'required', 
        ]);

        $member = Auth::guard("member")->user();

        if (!$member) {
            return response()->json(['status' => 'failed', 'message' => 'Unauthorized'], 401);
        }

        $like = Like::where('post_id', $request->post_id)
                    ->where('member_id', $member->id)
                    ->first();

        if ($like) {
            $like->update([
                'type' => $validated['type']
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Reaction updated successfully',
                'data' => $like
            ]);
        }

        return response()->json([
            'status' => 'failed',
            'message' => 'No reaction found to update. Please like first.'
        ], 404);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'post_id' => 'required',
        ]);

        $member = Auth::guard("member")->user();

        if (!$member) {
            return response()->json(['status' => 'failed', 'message' => 'Unauthorized'], 401);
        }

        $like = Like::where('post_id', $request->post_id)
                    ->where('member_id', $member->id)
                    ->first();

        if ($like) {
            $like->delete();
            return response()->json([
                'status'  => 'success',
                'message' => 'Like deleted successfully'
            ]);
        }

        return response()->json([
            'status'  => 'failed',
            'message' => 'Like not found'
        ], 404);
    }


}
