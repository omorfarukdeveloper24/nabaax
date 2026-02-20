<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Earning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonetizationService {
    
    protected $view_rate = 1.50; 
    protected $watch_hour_rate = 5.00;

    public function processMember($member) {
        DB::beginTransaction();
        try {
            // মেম্বার অবজেক্ট রিফ্রেশ করে নেওয়া ভালো যাতে লেটেস্ট ডাটা থাকে
            $member->refresh();

            if ($member->monetization == 0) {
                $this->checkEligibility($member);
            } else {
                $this->calculateIncome($member);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Monetization Error [Member ID: {$member->id}]: " . $e->getMessage());
        }
    }

    private function checkEligibility($member) {
        $myPostIds = DB::table('posts')->where('member_id', $member->id)->pluck('id');

        $followers = DB::table('follows')->where('following_id', $member->id)->count();
        $partners = Member::where('referrer_id', $member->id)->count();
        $refers = Member::where('only_reffer', $member->id)->count();
        
        $current_views = DB::table('post_views')->whereIn('post_id', $myPostIds)->count();
        $watch_time = DB::table('video_views')
            ->whereIn('post_media_id', function($query) use ($myPostIds) {
                $query->select('id')->from('post_media')->whereIn('post_id', $myPostIds);
            })->sum('watch_time') ?? 0;

        if ($followers >= 2 || $partners >= 2 || $refers >= 2 || $watch_time >= 500) {
            $member->update([
                'monetization' => 1,
                'monetization_activated_at' => now(),
                'initial_views' => $current_views,
                'initial_watch_time' => $watch_time,
                'last_paid_views' => $current_views,
                'last_paid_watch_time' => $watch_time
            ]);
            Log::info("Monetization ENABLED for Member ID: {$member->id}");
        }
    }

    private function calculateIncome($member) {
        $myPostIds = DB::table('posts')->where('member_id', $member->id)->pluck('id');

        // ১. বর্তমান ভিউ এবং ওয়াচ টাইম সংগ্রহ
        $current_views = DB::table('post_views')->whereIn('post_id', $myPostIds)->count();
        $current_watch_time = DB::table('video_views')
            ->whereIn('post_media_id', function($query) use ($myPostIds) {
                $query->select('id')->from('post_media')->whereIn('post_id', $myPostIds);
            })->sum('watch_time') ?? 0;
        
        $new_views = $current_views - ($member->last_paid_views ?? 0);
        $new_watch_sec = $current_watch_time - ($member->last_paid_watch_time ?? 0);

        // ২. নতুন কিছু যুক্ত হলে তবেই ক্যালকুলেশন হবে
        if ($new_views > 0 || $new_watch_sec > 0) {
            $view_money = ($new_views / 1000) * $this->view_rate;
            $watch_money = ($new_watch_sec / 3600) * $this->watch_hour_rate;
            $total_today = round($view_money + $watch_money, 4);

            if ($total_today > 0) {
                // ৩. আর্নিং টেবিল আপডেট
                Earning::updateOrCreate(
                    ['member_id' => $member->id, 'earning_date' => now()->format('Y-m-d')],
                    [
                        'amount' => DB::raw("COALESCE(amount, 0) + $total_today"),
                        'new_views' => DB::raw("COALESCE(new_views, 0) + $new_views"),
                        'new_watch_time' => DB::raw("COALESCE(new_watch_time, 0) + $new_watch_sec")
                    ]
                );

                // ৪. মেম্বার টেবিল আপডেট
                $member->update([
                    'balance' => $member->balance + $total_today,
                    'total_earned' => $member->total_earned + $total_today,
                    'last_paid_views' => $current_views,
                    'last_paid_watch_time' => $current_watch_time
                ]);

                Log::info("Income Processed: Member #{$member->id} earned {$total_today}");
            }
        }
    }
}