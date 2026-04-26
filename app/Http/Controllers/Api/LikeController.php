<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Like;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Jobs\UpdateLikeCountJob;
use App\Jobs\SendLikeNotificationJob;

class LikeController extends Controller
{
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
                'message' => 'Unauthorized user'
            ], 401);
        }

        $like_count    = Like::where('post_id', $request->id)->where('type', 1)->count();
        $dislike_count = Like::where('post_id', $request->id)->where('type', 2)->count();

        return response()->json([
            'status'        => 'success',
            'like_count'    => $like_count,
            'dislike_count' => $dislike_count,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'post_id' => 'required|exists:posts,id',
            'type'    => 'required|in:1,2', // 1 = like, 2 = dislike
        ]);

        $member = Auth::guard("member")->user();
        if (!$member) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Unauthorized user'
            ], 401);
        }

        $postId   = (int) $validated['post_id'];
        $newType  = (int) $validated['type'];
        $memberId = $member->id;

        // Post owner কে খুঁজে বের করা
        $post = Post::find($postId);

        // আগের reaction আছে কিনা চেক
        $existingLike = Like::where('post_id', $postId)
                            ->where('member_id', $memberId)
                            ->first();

        try {
            DB::beginTransaction();

            if ($existingLike) {
                if ($existingLike->type == $newType) {
                    // একই button আবার চাপলে — toggle off (remove)
                    $existingLike->delete();
                    DB::commit();

                    // count decrement — background Job
                    $action = $newType == 1 ? 'like_decrement' : 'dislike_decrement';
                    UpdateLikeCountJob::dispatch($postId, $action)
                        ->onQueue('default');

                    return response()->json([
                        'status'  => 'success',
                        'message' => 'Reaction removed',
                        'data'    => null,
                    ]);

                } else {
                    // like → dislike অথবা dislike → like (switch)
                    $oldType = $existingLike->type;
                    $existingLike->update(['type' => $newType]);
                    DB::commit();

                    // পুরনোটা কমাও, নতুনটা বাড়াও — background Job
                    $decrementAction = $oldType == 1 ? 'like_decrement' : 'dislike_decrement';
                    $incrementAction = $newType == 1 ? 'like_increment' : 'dislike_increment';

                    UpdateLikeCountJob::dispatch($postId, $decrementAction)->onQueue('default');
                    UpdateLikeCountJob::dispatch($postId, $incrementAction)->onQueue('default');

                    // notification — নিজের post হলে পাঠাবে না
                    if ($post && $post->member_id !== $memberId) {
                        $reactionType = $newType == 1 ? 'like' : 'dislike';
                        SendLikeNotificationJob::dispatch(
                            $post->member_id,
                            $member->name,
                            $postId,
                            $reactionType
                        )->onQueue('notifications');
                    }

                    return response()->json([
                        'status'  => 'success',
                        'message' => 'Reaction updated',
                        'data'    => $existingLike,
                    ]);
                }

            } else {
                // একদম নতুন like/dislike
                $like = Like::create([
                    'post_id'   => $postId,
                    'member_id' => $memberId,
                    'type'      => $newType,
                ]);
                DB::commit();

                // count increment — background Job
                $action = $newType == 1 ? 'like_increment' : 'dislike_increment';
                UpdateLikeCountJob::dispatch($postId, $action)->onQueue('default');

                // notification — নিজের post হলে পাঠাবে না
                if ($post && $post->member_id !== $memberId) {
                    $reactionType = $newType == 1 ? 'like' : 'dislike';
                    SendLikeNotificationJob::dispatch(
                        $post->member_id,
                        $member->name,
                        $postId,
                        $reactionType
                    )->onQueue('notifications');
                }

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Reaction saved',
                    'data'    => $like,
                ]);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("LikeController store Error: " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong!',
            ], 500);
        }
    }

    public function details($id)
    {
        $like = Like::with(['post', 'member'])->findOrFail($id);
        return response()->json($like);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'post_id' => 'required|exists:posts,id',
        ]);

        $member = Auth::guard("member")->user();
        if (!$member) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Unauthorized'
            ], 401);
        }

        $like = Like::where('post_id', $request->post_id)
                    ->where('member_id', $member->id)
                    ->first();

        if (!$like) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Like not found'
            ], 404);
        }

        $postId = $like->post_id;
        $type   = $like->type;
        $like->delete();

        // count decrement — background Job
        $action = $type == 1 ? 'like_decrement' : 'dislike_decrement';
        UpdateLikeCountJob::dispatch($postId, $action)->onQueue('default');

        return response()->json([
            'status'  => 'success',
            'message' => 'Like deleted successfully',
        ]);
    }
}