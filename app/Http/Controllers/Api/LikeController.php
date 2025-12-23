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


    public function details($id)
    {
        $like = Like::with(['post', 'member'])->findOrFail($id);
        return response()->json($like);
    }

    public function update(Request $request)
    {
        // ১. ভ্যালিডেশন
        $validated = $request->validate([
            'post_id' => 'required',
            'type'    => 'required', // নতুন টাইপ (যেমন: Like=1, Love=2)
        ]);

        $member = Auth::guard("member")->user();

        if (!$member) {
            return response()->json(['status' => 'failed', 'message' => 'Unauthorized'], 401);
        }

        // ২. আগের দেওয়া লাইকটি খুঁজে বের করা
        $like = Like::where('post_id', $request->post_id)
                    ->where('member_id', $member->id)
                    ->first();

        if ($like) {
            // ৩. যদি লাইক খুঁজে পাওয়া যায়, তবে টাইপ আপডেট করা
            $like->update([
                'type' => $validated['type']
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Reaction updated successfully',
                'data' => $like
            ]);
        }

        // ৪. যদি কোনো লাইক রেকর্ড না থাকে
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
