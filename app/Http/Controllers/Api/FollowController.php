<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Follow;
use App\Models\FollowBoost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FollowController extends Controller
{
   
    
    // public function follow(Request $request)
    // {
    //     $follower = Auth::guard('member')->user();
    //     $following_id = $request->id;

    //     if ($follower->id == $following_id) {
    //         return response()->json(['message' => 'নিজেকে follow করা সম্ভব নয়!'], 400);
    //     }

    //     $exists = Follow::where('follower_id', $follower->id)
    //         ->where('following_id', $following_id)
    //         ->exists();

    //     if ($exists) {
    //         return response()->json(['message' => 'আপনি ইতিমধ্যে এই সদস্যকে follow করেছেন!']);
    //     }

    //     Follow::create([
    //         'follower_id' => $follower->id,
    //         'following_id' => $following_id,
    //     ]);

    //     return response()->json(['message' => 'Follow সফলভাবে সম্পন্ন হয়েছে!']);
    // }
    
    
   public function follow(Request $request)
    {
        $follower = Auth::guard('member')->user();
        $following_id = $request->id;
    
        if ($follower->id == $following_id) {
            return response()->json(['message' => 'You cannot follow yourself!'], 400);
        }
    
        $exists = Follow::where('follower_id', $follower->id)
            ->where('following_id', $following_id)
            ->exists();
    
        if ($exists) {
            return response()->json(['message' => 'You are already following this member!'], 400);
        }
    
        
        $boost = FollowBoost::where('member_id', $following_id)
            ->where('status', 'active')
            ->first();
    
        $followType = 0; 
    
        if ($boost && $boost->remaining_amount > 0) {
            $follower->increment('balance', 1);
            $boost->decrement('remaining_amount', 1);
    
            if ($boost->remaining_amount <= 1) {
                $boost->update(['status' => 'completed']);
            }
    
            $followType = 1; 
        }
       

        Follow::create([
            'follower_id' => $follower->id,
            'following_id' => $following_id,
            'type' => $followType
        ]);
    
        return response()->json(['message' => 'Follow completed successfully!']);
    }

    
    
    public function unfollow(Request $request)
    {
        $follower = Auth::guard('member')->user();
        $follow = Follow::where('follower_id', $follower->id)
            ->where('following_id', $request->following_id)
            ->first();
    
        if (!$follow) {
            return response()->json(['message' => 'Follow record not found.'], 404);
        }
    
        if ($follow->type == 1) {
            return response()->json([
                'message' => 'You cannot unfollow this member because you are earning from their boost.'
            ], 400);
        }
    
        $follow->delete();
    
        return response()->json(['message' => 'Follow has been successfully removed.']);
    }


  
    public function followers()
    {
        $member = Auth::guard('member')->user();

        $followers = Follow::where('following_id', $member->id)
            ->with('follower:id,name,username,image')
            ->get()
            ->map(function ($follow) {
                return [
                    'id' => $follow->follower->id,
                    'name' => $follow->follower->name,
                    'username' => $follow->follower->username,
                    'image' => $follow->follower->image,
                ];
            });

        return response()->json(['data' => $followers]);
    }

    
    public function following()
    {
        $member = Auth::guard('member')->user();

        $following = Follow::where('follower_id', $member->id)
            ->with('following:id,name,username,image')
            ->get()
            ->map(function ($follow) {
                return [
                    'id' => $follow->following->id,
                    'name' => $follow->following->name,
                    'name' => $follow->following->name,
                    'username' => $follow->following->username,
                    'image' => $follow->following->image,
                ];
            });

        return response()->json(['data' => $following]);
    }
    
   
    
    public function followBoost(Request $request)
    {
    
        $validator = Validator::make($request->all(), [
            'amount' => 'required|integer|min:50'
        ], [
            'amount.required' => 'Please Enter your amount',
            'amount.integer'  => 'পmust be Numbner',
            'amount.min'      => 'Minimum 50 taka ',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ]);
        }
    
        $member = Auth::guard('member')->user();
     
        
        if (!$member) {
            return response()->json(['error' => 'Member not found. Please log in first.'], 404);
        }
    
        if ($member->verified != 1) {
            return response()->json(['error' => 'Please verify your account before starting a boost.'], 403);
        }
        
        
        $activeBoost = FollowBoost::where('member_id', $member->id)
            ->where('status', 'active')
            ->first();
    
        if ($activeBoost) {
            return response()->json([
                'error' => 'You already have an active boost. Please wait until it is completed.'
            ], 400);
        }
        
        $amount = $request->amount;
    
        if ($member->balance < $amount) {
            return response()->json(['error' => 'Insufficient balance in your account.'], 400);
        }
    
        $member->decrement('balance', $amount);
    
        $followBoost = FollowBoost::create([
            'member_id' => $member->id,
            'total_amount' => $amount,
            'remaining_amount' => $amount,
            'status' => 'active',
        ]);
    
        return response()->json([
            'message' => 'Follow boost started successfully!',
            'boost' => $followBoost
        ], 200);
    }

    
    
}
