<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Earning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonetizationService {
    // প্রতি ১০০০ ভিউ এবং ১ ঘণ্টা ওয়াচ টাইমের রেট (টাকায়)
    protected $view_rate = 1.50; 
    protected $watch_hour_rate = 5.00;

    public function processMember($member) {
        DB::beginTransaction();
        try {
            // ১. মনিটাইজেশন যোগ্য কি না চেক করা (যদি অফ থাকে)
            if ($member->monetization == 0) {
                $this->checkEligibility($member);
            } 
            // ২. যদি মনিটাইজড হয়, তবে ইনকাম হিসাব করা
            else {
                $this->calculateIncome($member);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error processing monetization for Member ID: {$member->id}. Error: " . $e->getMessage());
        }
    }

    private function checkEligibility($member) {
        // আপনার ৩টি শর্তের যেকোনো একটি
        $followers = DB::table('follows')->where('following_id', $member->id)->count();
        $partners = Member::where('referrer_id', $member->id)->count();
        $refers = Member::where('only_reffer', $member->id)->count();
        $watch_time = DB::table('video_views')->where('member_id', $member->id)->sum('watch_time');

        if ($followers >= 1000 || $partners >= 10 || $refers >= 100 || $watch_time >= 14400000) {
            $member->update([
                'monetization' => 1,
                'monetization_activated_at' => now(),
                'initial_views' => $member->total_views,
                'initial_watch_time' => $watch_time,
                'last_paid_views' => $member->total_views,
                'last_paid_watch_time' => $watch_time
            ]);
            Log::info("Member ID {$member->id} is now Monetized!");
        }
    }

    private function calculateIncome($member) {
        $current_watch_time = DB::table('video_views')->where('member_id', $member->id)->sum('watch_time');
        
        // নতুন কতটুকু ভিউ ও ওয়াচ টাইম হলো (স্ন্যাপশট বিয়োগফল)
        $new_views = $member->total_views - $member->last_paid_views;
        $new_watch_sec = $current_watch_time - $member->last_paid_watch_time;

        if ($new_views > 0 || $new_watch_sec > 0) {
            $view_money = ($new_views / 1000) * $this->view_rate;
            $watch_money = ($new_watch_sec / 3600) * $this->watch_hour_rate;
            $total_today = round($view_money + $watch_money, 2);

            if ($total_today > 0) {
                // ১. আর্নিং টেবিল এন্ট্রি
                Earning::create([
                    'member_id' => $member->id,
                    'amount' => $total_today,
                    'new_views' => $new_views,
                    'new_watch_time' => $new_watch_sec,
                    'earning_date' => now()->subDay()->format('Y-m-d') // গতকালকের আয়
                ]);

                // ২. মেম্বার ব্যালেন্স আপডেট
                $member->increment('balance', $total_today);
                $member->increment('total_earned', $total_today);
                
                // ৩. লাস্ট পেইড কাউন্টার আপডেট (খুবই জরুরি)
                $member->update([
                    'last_paid_views' => $member->total_views,
                    'last_paid_watch_time' => $current_watch_time
                ]);
            }
        }
    }
}