<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post_stat;
use Illuminate\Support\Facades\Auth;

class PostStatController extends Controller
{
    function __construct()
    {
        $this->middleware("auth.jwt", [
            
        ]);
    }

    
    public function list()
    {
        return response()->json(Post_stat::all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'post_id' => 'required|exists:posts,id',
            'likes_count' => 'nullable|integer|min:0',
            'comments_count' => 'nullable|integer|min:0',
            'shares_count' => 'nullable|integer|min:0',
            'views_count' => 'nullable|integer|min:0',
            'last_viewed_at' => 'nullable|date',
        ]);

        $stat = Post_stat::create($request->all());

        return response()->json($stat, 201);
    }

    public function details($id)
    {
        $stat = Post_stat::findOrFail($id);
        return response()->json($stat);
    }

    public function update(Request $request, $id)
    {
        $stat = Post_stat::findOrFail($id);

        $request->validate([
            'likes_count' => 'nullable|integer|min:0',
            'comments_count' => 'nullable|integer|min:0',
            'shares_count' => 'nullable|integer|min:0',
            'views_count' => 'nullable|integer|min:0',
            'last_viewed_at' => 'nullable|date',
        ]);

        $stat->update($request->all());

        return response()->json($stat);
    }

    public function destroy($id)
    {
        $stat = Post_stat::findOrFail($id);
        $stat->delete();

        return response()->json(null, 204);
    }

}
