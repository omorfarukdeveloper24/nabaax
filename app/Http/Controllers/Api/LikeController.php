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

    public function update(Request $request, $id)
    {
        $like = Like::findOrFail($id);

        $validated = $request->validate([
            'type' => 'required|in:like,dislike',
        ]);

        $like->update($validated);

        return response()->json([
            'message' => 'Update Success',
            'data' => $like
        ]);
    }

    public function destroy($id)
    {
        $like = Like::findOrFail($id);
        $like->delete();

        return response()->json([
            'message' => 'Delete success '
        ]);
    }


}
