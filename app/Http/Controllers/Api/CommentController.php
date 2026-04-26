<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use App\Traits\NotificationTrait;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use App\Jobs\SendMentionNotificationJob;
use App\Jobs\UpdateCommentCountJob;

class CommentController extends Controller
{
    use NotificationTrait;

    function __construct()
    {
        $this->middleware("auth.jwt", []);
    }

    public function list(Request $request)
    {
        $member = Auth::guard('member')->user();

        if (!$member) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Unauthorized user',
            ], 401);
        }

        if (!Post::where('id', $request->id)->exists()) {
            return response()->json([
                'status'  => 'failed',
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
            return response()->json([
                'status'  => 'failed',
                'message' => 'Parent ID is required',
            ], 400);
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

    public function store(Request $request)
    {
        // ১. মেম্বার অথেন্টিকেশন চেক
        $member = Auth::guard('member')->user();
        if (!$member) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Unauthorized user',
            ], 401);
        }

        // ২. ইনপুট ভ্যালিডেশন
        $request->validate([
            'post_id'   => 'required|exists:posts,id',
            'content'   => 'required|string',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        try {
            DB::beginTransaction();

            // ৩. কমেন্ট সেভ করা
            $comment = Comment::create([
                'post_id'   => $request->post_id,
                'content'   => $request->content,
                'parent_id' => $request->parent_id,
                'member_id' => $member->id,
            ]);

            DB::commit();
            // ⚠️ DB commit-এর পরে dispatch — transaction-এর বাইরে

            // ৪. comment_count increment — background Job
            UpdateCommentCountJob::dispatch($request->post_id, 'increment')
                ->onQueue('default');

            // ৫. mention notification — background Job
            preg_match_all('/@\[.*?\]\((\d+)\)/', $request->content, $matches);
            $mentionedIds = array_unique($matches[1]);

            foreach ($mentionedIds as $id) {
                if ($id != $member->id) {
                    SendMentionNotificationJob::dispatch(
                        (int) $id,
                        $member->name,
                        (int) $request->post_id,
                        $comment->id
                    )->onQueue('notifications');
                }
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Comment submitted successfully',
                'data'    => $comment->load('member'),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Comment Store Error: " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong!',
                'error'   => $e->getMessage(),
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
        $member  = Auth::guard('member')->user();
        $comment = Comment::findOrFail($id);

        // নিজের comment কিনা check
        if ($comment->member_id !== $member->id) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $comment->update($validated);

        return response()->json([
            'message' => 'কমেন্ট সফলভাবে আপডেট হয়েছে',
            'data'    => $comment,
        ]);
    }

    public function destroy($id)
    {
        $member  = Auth::guard('member')->user();
        $comment = Comment::findOrFail($id);

        // নিজের comment কিনা check
        if ($comment->member_id !== $member->id) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Unauthorized',
            ], 403);
        }

        $postId = $comment->post_id;
        $comment->delete();

        // comment_count decrement — background Job
        UpdateCommentCountJob::dispatch($postId, 'decrement')
            ->onQueue('default');

        return response()->json([
            'message' => 'কমেন্ট ডিলিট করা হয়েছে',
        ]);
    }
}