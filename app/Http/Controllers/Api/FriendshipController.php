<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Friendship;
use App\Models\Member;
use Illuminate\Support\Facades\Auth;

class FriendshipController extends Controller
{
    
    // public function sendRequest(Request $request)
    // {
        
    //     $senderId = Auth::guard('member')->id();
        

    //     if ($senderId == $request->id) {
    //         return response()->json(['error' => 'Sorry This is your Id'], 400);
    //     }

    //     $exists = Friendship::where(function ($q) use ($senderId, $request->id) {
    //         $q->where('sender_id', $senderId)->where('receiver_id', $request->id);
    //     })->orWhere(function ($q) use ($senderId, $request->id) {
    //         $q->where('sender_id', $request->id)->where('receiver_id', $senderId);
    //     })->first();

    //     if ($exists) {
    //         return response()->json(['error' => 'Already you ar frined'], 400);
    //     }

    //     Friendship::create([
    //         'sender_id' => $senderId,
    //         'receiver_id' => $request->id,
    //         'status' => 'pending',
    //     ]);

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Frined Requset successfully',
    //         'data' => []
    //         ]);
    // }
    
    
    
    public function sendRequest(Request $request)
    {
        $sender = Auth::guard('member')->user();
    
        if (!$sender) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Unauthorized user'
            ], 401);
        }
    
        $receiverId = $request->id;
    
       
        if ($sender->id == $receiverId) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Sorry, you cannot send a request to yourself.'
            ], 400);
        }
    
        $exists = Friendship::where(function ($q) use ($sender, $receiverId) {
                $q->where('sender_id', $sender->id)
                  ->where('receiver_id', $receiverId);
            })
            ->orWhere(function ($q) use ($sender, $receiverId) {
                $q->where('sender_id', $receiverId)
                  ->where('receiver_id', $sender->id);
            })
            ->first();
    
        if ($exists) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Friend request already exists.'
            ], 400);
        }
    
        
        Friendship::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiverId,
            'status' => 'pending',
        ]);
    
        return response()->json([
            'status' => 'success',
            'message' => 'Friend request sent successfully!',
        ], 200);
    }
    
    public function pendingRequests()
    {
        $receiverId = Auth::guard('member')->id(); 
    
        $pendingRequests = Friendship::where('receiver_id', $receiverId)
            ->where('status', 'pending')
            ->with('sender') 
            ->get();
    
        if ($pendingRequests->isEmpty()) {
            return response()->json(['message' => 'No pending friend requests found']);
        }
    
        return response()->json([
            'pending_requests' => $pendingRequests
        ]);
    }


    public function acceptRequest(Request $request)
    {
        $receiverId = Auth::guard('member')->id();
        $senderId = $request->id;
        
        $friendship = Friendship::where('receiver_id', $receiverId)
            ->where('sender_id', $senderId)
            ->where('status', 'pending')
            ->first();
        
        
        if (!$friendship) {
            return response()->json(['error' => 'Request not Found'], 404);
        }
        

        $friendship->update(['status' => 'accepted']);

        return response()->json(['success' => 'Friend request accepted']);
    }

    
    public function rejectRequest(Request $request)
    {
       $receiverId = Auth::guard('member')->id();
       $senderId = $request->id;
       
        
        $friendship = Friendship::where('sender_id', $senderId)
            ->where('receiver_id', $receiverId)
            ->where('status', 'pending')
            ->first();
        
        
        
        if (!$friendship) {
            return response()->json(['error' => 'Request not Found'], 404);
        }

        $friendship->update(['status' => 'rejected']);

        return response()->json(['success' => 'Friend request Rejected']);
    }
    
    public function unfriend(Request $request)
    {
        $memberId = Auth::guard('member')->id();
    
        $friendship = Friendship::where(function($q) use ($memberId, $request) {
            $q->where('sender_id', $memberId)->where('receiver_id', $request->id);
        })->orWhere(function($q) use ($memberId, $request) {
            $q->where('sender_id', $request->id)->where('receiver_id', $memberId);
        })->where('status', 'accepted')->first();
    
        if (!$friendship) {
            return response()->json(['error' => 'You are not friends']);
        }
    
        $friendship->delete();
    
        return response()->json(['success' => 'Unfriended successfully']);
    }

    
    public function friendlist()
    {
        $member = Auth::guard('member')->user();
    
        $friendships = Friendship::where(function ($q) use ($member) {
            $q->where('sender_id', $member->id)
              ->orWhere('receiver_id', $member->id);
        })
        ->where('status', 'accepted')
        ->with(['sender', 'receiver'])
        ->get();
    
        
        $friends = $friendships->map(function ($friendship) use ($member) {
            return $friendship->sender_id == $member->id
                ? $friendship->receiver   
                : $friendship->sender;  
        });
    
        return response()->json($friends);
    }
}
