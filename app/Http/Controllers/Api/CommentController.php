<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use App\Traits\NotificationTrait;

class CommentController extends Controller
{
    use NotificationTrait;
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
    
    //     if (!Post::where('id', $request->id)->exists()) {
    //         return response()->json([
    //             'status' => 'failed',
    //             'message' => 'Post not found',
    //         ], 404);
    //     }
        
        
        
        
    //     $comments = Comment::select('id', 'post_id', 'member_id', 'parent_id', 'content', 'updated_at')
    //     ->where('post_id', $request->id)
    //     ->whereNull('parent_id')
    //     ->with([
    //         'member:id,name,username,image',
    //         'replies' => function ($query) {
    //             $query->select('id', 'post_id', 'member_id', 'parent_id', 'content', 'updated_at')
    //                   ->with('member:id,name,username,image');
    //         }
    //     ])
    //     ->latest()
    //     ->paginate(50);
        
        
        
        
    
    //     return response()->json([
    //         'status' => 'success',
    //         'data'   => $comments,
    //     ], 200);
    // }
    
    
    // public function list()
    // {
    //     $comments = Comment::with(['post', 'member', 'parent'])->latest()->get();
    //     return response()->json($comments);
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
            ->with('member:id,name,username,image') 
            ->withCount('replies') 
            ->latest()
            ->paginate(50);

        return response()->json([
            'status' => 'success',
            'data'   => $comments,
        ], 200);
    }

    public function getReplies(Request $request)
    {
        
        $parentId = $request->parent_id;

        if (!$parentId) {
            return response()->json(['status' => 'failed', 'message' => 'Parent ID is required'], 400);
        }

        $replies = Comment::select('id', 'post_id', 'member_id', 'parent_id', 'content', 'updated_at')
            ->where('parent_id', $parentId)
            ->with('member:id,name,username,image')
            ->withCount('replies')
            ->oldest() 
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $replies,
        ], 200);
    }

    // public function store(Request $request)
    // {
    //     // return $request;
        
    //     $member = Auth::guard('member')->user();
    //     if (!$member) {
    //         return response()->json([
    //             'status' => 'failed',
    //             'message' => 'Unauthorized user'
    //         ], 401);
    //     }
    
    //     $validated = $request->validate([
    //         'post_id'   => 'required',
    //         'content'   => 'required',
    //         'parent_id' => 'nullable',
    //     ]);

    //     $validated['member_id'] = $member->id;
        
    //     // return $validated;
        
    //     $comment = Comment::create($validated);
    //     return response()->json([
    //         'status'  => 'success',
    //         'message' => 'Comment submitted successfully',
    //         'data'    => $comment,
    //     ], 200);
    // }

    public function store(Request $request)
    {
        // ১. মেম্বার অথেন্টিকেশন চেক
        $member = Auth::guard('member')->user();
        if (!$member) {
            return response()->json(['status' => 'failed', 'message' => 'Unauthorized user'], 401);
        }

        // ২. ইনপুট ভ্যালিডেশন
        $request->validate([
            'post_id'   => 'required|exists:posts,id',
            'content'   => 'required|string', // ফরম্যাট: "Hello @[Siam](10)"
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        try {
            DB::beginTransaction(); // ডাটা সেফটির জন্য ট্রানজ্যাকশন শুরু

            // ৩. কমেন্ট সেভ করা
            $comment = Comment::create([
                'post_id'   => $request->post_id,
                'content'   => $request->content,
                'parent_id' => $request->parent_id,
                'member_id' => $member->id,
            ]);

            // ৪. মেনশন আইডি খুঁজে বের করা (Regex)
            preg_match_all('/@\[.*?\]\((\d+)\)/', $request->content, $matches);
            $mentionedIds = array_unique($matches[1]);

            if (!empty($mentionedIds)) {
                // ৫. নোটিফিকেশন পাঠানোর প্রস্তুতি
                $title = "New Mention";
                $body  = "{$member->name} mentioned you in a comment.";
                
                // ডাটা হিসেবে আইডি পাঠিয়ে রাখা যাতে অ্যাপে ক্লিক করলে ঐ পোস্টে নিয়ে যায়
                $notifData = [
                    'type'    => 'mention',
                    'post_id' => (string)$request->post_id,
                    'comment_id' => (string)$comment->id
                ];

                foreach ($mentionedIds as $id) {
                    // নিজেকে নিজে মেনশন করলে নোটিফিকেশন পাঠানোর দরকার নেই
                    if ($id != $member->id) {
                        // আপনার Trait এর ফাংশনটি এখানে কল করা হচ্ছে
                        $this->sendFcmNotification($id, $title, $body, $notifData);
                    }
                }
            }

            DB::commit(); // সব ঠিক থাকলে ডাটাবেজে পার্মানেন্ট সেভ হবে

            return response()->json([
                'status'  => 'success',
                'message' => 'Comment submitted successfully',
                'data'    => $comment->load('member'),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack(); // কোনো এরর হলে আগের সবকিছু বাতিল হয়ে যাবে
            \Log::error("Comment Store Error: " . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong!',
                'error' => $e->getMessage()
            ], 500);
        }
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
