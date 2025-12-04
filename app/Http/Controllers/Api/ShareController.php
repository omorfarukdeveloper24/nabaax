<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Share;
use Illuminate\Support\Facades\Auth;

class ShareController extends Controller
{
    function __construct()
    {
        $this->middleware("auth.jwt", [
            
        ]);
    }
    
    public function list()
    {
        $shares = Share::with(['member', 'post'])->latest()->get();
        return response()->json($shares);
    }

    
    public function store(Request $request)
    {
        $request->validate([
            'post_id'   => 'required|exists:posts,id',
            'member_id' => 'required|exists:members,id',
            'type'      => 'required|in:share,repost',
            'comment'   => 'nullable|string'
        ]);

        $share = Share::create([
            'post_id'   => $request->post_id,
            'member_id' => $request->member_id,
            'type'      => $request->type,
            'comment'   => $request->comment,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Share created successfully!',
            'data'    => $share
        ]);
    }

    
    public function details($id)
    {
        $share = Share::with(['member', 'post'])->find($id);

        if (!$share) {
            return response()->json(['status' => false, 'message' => 'Share not found'], 404);
        }

        return response()->json($share);
    }

    public function update(Request $request, $id)
    {
        $share = Share::find($id);

        if (!$share) {
            return response()->json(['status' => false, 'message' => 'Share not found'], 404);
        }

        $share->update($request->only(['type', 'comment']));

        return response()->json([
            'status'  => true,
            'message' => 'Share updated successfully!',
            'data'    => $share
        ]);
    }

    

    public function destroy($id)
    {
        $share = Share::find($id);

        if (!$share) {
            return response()->json(['status' => false, 'message' => 'Share not found'], 404);
        }

        $share->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Share deleted successfully!'
        ]);
    }


    
}
