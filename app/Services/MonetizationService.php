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
        // ট্রানজেকশন ব্যবহার করা হয়েছে যাতে ডাটাবেজ ইনকনসিস্টেন্সি না হয়
        DB::beginTransaction();
        try {
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
        // এই মেম্বারের নিজের পোস্টগুলোর আইডি সংগ্রহ
        $myPostIds = DB::table('posts')->where('member_id', $member->id)->pluck('id');

        // ১. মনিটাইজেশন ক্রাইটেরিয়া ডেটা সংগ্রহ
        $followers = DB::table('follows')->where('following_id', $member->id)->count();
        $partners = Member::where('referrer_id', $member->id)->count();
        $refers = Member::where('only_reffer', $member->id)->count();
        
        // নিজের পোস্টগুলোর ওপর আসা ভিউ এবং ওয়াচ টাইম
        $current_views = DB::table('post_views')->whereIn('post_id', $myPostIds)->count();
        $watch_time = DB::table('video_views')
            ->whereExists(function ($query) use ($myPostIds) {
                $query->select(DB::raw(1))
                      ->from('post_media')
                      ->whereColumn('post_media.id', 'video_views.post_media_id')
                      ->whereIn('post_media.post_id', $myPostIds);
            })->sum('watch_time') ?? 0;

        // ২. শর্ত যাচাই
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
        // ১. অথর (Author) লজিক: মেম্বারের পোস্ট আইডিগুলো খুঁজে বের করা
        $myPostIds = DB::table('posts')->where('member_id', $member->id)->pluck('id');

        // ২. ডাটাবেজ থেকে বর্তমান পরিসংখ্যান (পোস্টের মালিক হিসেবে)
        $current_views = DB::table('post_views')->whereIn('post_id', $myPostIds)->count();
        
        // ভিডিও ওয়াচ টাইম (Post Media হয়ে মূল পোস্টে কানেক্ট করা)
        $current_watch_time = DB::table('video_views')
            ->whereExists(function ($query) use ($myPostIds) {
                $query->select(DB::raw(1))
                      ->from('post_media')
                      ->whereColumn('post_media.id', 'video_views.post_media_id')
                      ->whereIn('post_media.post_id', $myPostIds);
            })->sum('watch_time') ?? 0;
        
        $today = now()->format('Y-m-d');

        // ৩. ইনক্রিমেন্টাল ডেটা ক্যালকুলেশন
        $new_views = $current_views - ($member->last_paid_views ?? 0);
        $new_watch_sec = $current_watch_time - ($member->last_paid_watch_time ?? 0);

        if ($new_views > 0 || $new_watch_sec > 0) {
            
            $view_money = ($new_views / 1000) * $this->view_rate;
            $watch_money = ($new_watch_sec / 3600) * $this->watch_hour_rate;
            $total_today_raw = $view_money + $watch_money;

            // ৪. পেমেন্ট প্রসেসিং (ক্ষুদ্রতম পেমেন্টও যেন লস না হয়)
            if ($total_today_raw > 0) {
                $total_today = round($total_today_raw, 4);

                // ৫. আর্নিং এবং মেম্বার টেবিল আপডেট
                Earning::updateOrCreate(
                    ['member_id' => $member->id, 'earning_date' => $today],
                    [
                        'amount' => DB::raw("amount + $total_today"),
                        'new_views' => DB::raw("new_views + $new_views"),
                        'new_watch_time' => DB::raw("new_watch_time + $new_watch_sec")
                    ]
                );

                // ব্যালেন্স ইনক্রিমেন্ট
                $member->increment('balance', $total_today);
                $member->increment('total_earned', $total_today);
                
                // ট্র্যাকিং পয়েন্ট আপডেট
                $member->update([
                    'last_paid_views' => $current_views,
                    'last_paid_watch_time' => $current_watch_time
                ]);

                Log::info("Income Processed: Member #{$member->id} earned {$total_today} TK.");
            }
        }
    }



}