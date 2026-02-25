<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Follow;
use App\Models\Member;
use App\Models\FollowBoost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FollowController extends Controller
{
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
            if ($boost->remaining_amount <= 0) {
                $boost->update(['status' => 'completed']);
            }
            $followType = 1; 
        }

        $is_mutual = Follow::where('follower_id', $following_id)
            ->where('following_id', $follower->id)
            ->first();

        $new_follow = Follow::create([
            'follower_id' => $follower->id,
            'following_id' => $following_id,
            'type' => $followType,
            'is_friend' => $is_mutual ? true : false 
        ]);

        if ($is_mutual) {
            $is_mutual->update(['is_friend' => true]);
            $message = 'You are now friends!';
        } else {
            $message = 'Follow completed successfully!';
        }

        return response()->json(['message' => $message]);
    }






    public function unfollow(Request $request)
    {
        $follower = Auth::guard('member')->user();
        $following_id = $request->following_id;

        $follow = Follow::where('follower_id', $follower->id)
            ->where('following_id', $following_id)
            ->first();

        if (!$follow) {
            return response()->json(['message' => 'Follow record not found.'], 404);
        }

        if ($follow->type == 1) {
            return response()->json(['message' => 'You cannot unfollow earning boosts.'], 400);
        }

        Follow::where('follower_id', $following_id)
            ->where('following_id', $follower->id)
            ->update(['is_friend' => false]);

        $follow->delete();

        return response()->json(['message' => 'Unfollowed successfully.']);
    }

  
    // public function followers()
    // {
    //     $member = Auth::guard('member')->user();
    //     $member = Member::where('id', $request->id)->first();

    //     $followers = Follow::where('following_id', $member->id)
    //         ->with('follower:id,name,username,image')
    //         ->get()
    //         ->map(function ($follow) {
    //             return [
    //                 'id' => $follow->follower->id,
    //                 'name' => $follow->follower->name,
    //                 'username' => $follow->follower->username,
    //                 'is_friend' => $follow->is_friend,
    //                 'image' => $follow->follower->image,
    //             ];
    //         });

    //     return response()->json(['data' => $followers]);
    // }

    public function followers(Request $request)
    {
        // ১. লগইন করা মেম্বারের আইডি নেওয়া
        $authId = Auth::guard('member')->id(); 

        // ২. যার প্রোফাইলের ফলোয়ার লিস্ট দেখা হচ্ছে তাকে খুঁজে বের করা
        $member = Member::where('id', $request->id)->first();

        if (!$member) {
            return response()->json(['status' => 'failed', 'message' => 'Member not found'], 404);
        }

        $followers = Follow::where('following_id', $member->id)
            ->with('follower:id,name,username,image')
            ->get()
            ->map(function ($follow) use ($authId) { // 'use ($authId)' অবশ্যই দিতে হবে
                
                // ৩. লগইন করা মেম্বার কি এই ফলোয়ারকে ফলো করছে?
                $isFollowing = 0; 
                if ($authId) {
                    // চেক করা হচ্ছে: ফলোয়ার আইডির সাথে আপনার আইডি'র ফলো রিলেশন আছে কি না
                    $check = Follow::where('follower_id', $authId)
                                ->where('following_id', $follow->follower->id)
                                ->exists();
                    $isFollowing = $check ? 1 : 0;
                }

                return [
                    'id' => $follow->follower->id,
                    'name' => $follow->follower->name,
                    'username' => $follow->follower->username,
                    'is_friend' => $follow->is_friend,
                    'image' => $follow->follower->image,
                    'login_flow' => $isFollowing, // আপনার চাওয়া অনুযায়ী ১ অথবা ০ দেখাবে
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $followers
        ]);
    }

    
    // public function following()
    // {
    //     $member = Auth::guard('member')->user();

    //     $following = Follow::where('follower_id', $member->id)
    //         ->with('following:id,name,username,image')
    //         ->get()
    //         ->map(function ($follow) {
    //             return [
    //                 'id' => $follow->following->id,
    //                 'name' => $follow->following->name,
    //                 'is_friend' => $follow->is_friend,
    //                 'username' => $follow->following->username,
    //                 'image' => $follow->following->image,
    //             ];
    //         });

    //     return response()->json(['data' => $following]);
    // }

    public function following(Request $request) // ১. Request $request যোগ করা হয়েছে
    {
        // ২. লগইন করা মেম্বারের আইডি নেওয়া
        $authId = Auth::guard('member')->id();

        // ৩. রিকোয়েস্ট আইডি অনুযায়ী মেম্বারকে খুঁজে বের করা 
        // (যাতে নিজের এবং অন্যের—উভয়ের ফলোয়িং লিস্ট দেখা যায়)
        $memberId = $request->id ?? $authId; 
        $member = Member::find($memberId);

        if (!$member) {
            return response()->json(['status' => 'failed', 'message' => 'Member not found'], 404);
        }

        $following = Follow::where('follower_id', $member->id)
            ->with('following:id,name,username,image')
            ->get()
            ->map(function ($follow) use ($authId) { // ৪. 'use ($authId)' যোগ করা হয়েছে
                
                // ৫. চেক করা হচ্ছে: লিস্টের মানুষগুলোকে আপনি (লগইন ইউজার) ফলো করছেন কি না
                $isFollowing = 0;
                if ($authId) {
                    $check = Follow::where('follower_id', $authId)
                                ->where('following_id', $follow->following->id)
                                ->exists();
                    $isFollowing = $check ? 1 : 0;
                }

                return [
                    'id' => $follow->following->id,
                    'name' => $follow->following->name,
                    'username' => $follow->following->username,
                    'is_friend' => $follow->is_friend,
                    'image' => $follow->following->image,
                    'login_flow' => $isFollowing, // আপনার চাওয়া অনুযায়ী ১ অথবা ০
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $following
        ]);
    }


    // public function flowfriend()
    // {
    //     $member = Auth::guard('member')->user();

    //     $friends = Follow::where('follower_id', $member->id)
    //         ->where('is_friend', 1) 
    //         ->with('following:id,name,username,image') 
    //         ->get()
    //         ->map(function ($follow) {
    //             return [
    //                 'id'       => $follow->following->id,
    //                 'name'     => $follow->following->name,
    //                 'username' => $follow->following->username,
    //                 'is_friend'=> $follow->is_friend,
    //                 'image'    => $follow->following->image,
    //             ];
    //         });

    //     return response()->json(['data' => $friends]);
    // }

    public function flowfriend(Request $request) // Request ইনজেক্ট করা হয়েছে
    {
        // ১. লগইন করা মেম্বারের আইডি নেওয়া
        $authId = Auth::guard('member')->id();

        // ২. যার ফ্রেন্ড লিস্ট দেখা হচ্ছে তাকে খুঁজে বের করা 
        // যদি রিকোয়েস্টে আইডি না থাকে, তবে লগইন ইউজারের আইডি নেওয়া হবে
        $memberId = $request->id ?? $authId;
        
        if (!$memberId) {
            return response()->json(['status' => 'failed', 'message' => 'No member id found'], 400);
        }

        // ৩. ফ্রেন্ড লিস্ট কুয়েরি করা
        $friends = Follow::where('follower_id', $memberId)
            ->where('is_friend', 1) 
            ->with('following:id,name,username,image') 
            ->get()
            ->map(function ($follow) use ($authId) { // $authId ব্যবহারের জন্য use ব্যবহার করা হয়েছে
                
                // ৪. চেক করা হচ্ছে: লিস্টের এই ফ্রেন্ডকে আপনি (Auth User) ফলো করছেন কি না
                $isFollowing = 0;
                if ($authId) {
                    $check = Follow::where('follower_id', $authId)
                                ->where('following_id', $follow->following->id)
                                ->exists();
                    $isFollowing = $check ? 1 : 0;
                }

                return [
                    'id'       => $follow->following->id,
                    'name'     => $follow->following->name,
                    'username' => $follow->following->username,
                    'is_friend'=> $follow->is_friend,
                    'image'    => $follow->following->image,
                    'login_flow' => $isFollowing, // আপনার চাওয়া অনুযায়ী ১ অথবা ০
                ];
            });

        return response()->json([
            'status' => 'success',
            'data'   => $friends
        ]);
    }




    public function suggestions()
    {
        $member = Auth::guard('member')->user();

        $following_ids = Follow::where('follower_id', $member->id)
            ->pluck('following_id')
            ->toArray();

        $suggestions = Member::where('id', '!=', $member->id) 
            ->whereNotIn('id', $following_ids) 
            ->inRandomOrder() 
            ->limit(20) 
            ->select('id', 'name', 'username', 'image')
            ->get()
            ->map(function ($suggested) {
                return [
                    'id' => $suggested->id,
                    'name' => $suggested->name,
                    'username' => $suggested->username,
                    'image' => $suggested->image,
                    'followers_count' => Follow::where('following_id', $suggested->id)->count(), 
                ];
            });

        return response()->json(['data' => $suggestions]);
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
