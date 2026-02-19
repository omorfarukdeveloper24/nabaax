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
        // ১. আলাদা আলাদা টেবিল থেকে ডাটা সংগ্রহ
        $followers = DB::table('follows')->where('following_id', $member->id)->count();
        $partners = Member::where('referrer_id', $member->id)->count();
        $refers = Member::where('only_reffer', $member->id)->count();
        
        // ভিউ আসবে post_views টেবিল থেকে
        $current_views = DB::table('post_views')->where('member_id', $member->id)->count();
        
        // ওয়াচ টাইম আসবে video_views টেবিল থেকে
        $watch_time = DB::table('video_views')->where('member_id', $member->id)->sum('watch_time');

        // ২. শর্ত যাচাই (যেকোনো একটি পূরণ হলেই হবে)
        if ($followers >= 2 || $partners >= 2 || $refers >= 2 || $watch_time >= 500) {
            $member->update([
                'monetization' => 1,
                'monetization_activated_at' => now(),
                'initial_views' => $current_views,
                'initial_watch_time' => $watch_time,
                'last_paid_views' => $current_views,
                'last_paid_watch_time' => $watch_time
            ]);
            Log::info("Member ID {$member->id} is now Monetized!");
        }
    }

    private function calculateIncome($member) {
        // ১. ডাটাবেজ থেকে বর্তমান পরিসংখ্যান সংগ্রহ (Latest Data)
        $current_views = DB::table('post_views')->where('member_id', $member->id)->count();
        $current_watch_time = DB::table('video_views')->where('member_id', $member->id)->sum('watch_time') ?? 0;
        
        $today = now()->format('Y-m-d');

        // ২. নতুন ভিউ এবং সময় বের করা
        $new_views = $current_views - ($member->last_paid_views ?? 0);
        $new_watch_sec = $current_watch_time - ($member->last_paid_watch_time ?? 0);

        // ৩. শুধুমাত্র ভিউ বা সময় বাড়লেই ক্যালকুলেশন শুরু হবে
        if ($new_views > 0 || $new_watch_sec > 0) {
            
            // ভিউ এবং ওয়াচ টাইম থেকে ইনকাম হিসাব (Precision বজায় রাখার জন্য রাউন্ড আগে করা যাবে না)
            $view_money = ($new_views / 1000) * $this->view_rate;
            $watch_money = ($new_watch_sec / 3600) * $this->watch_hour_rate;
            
            $total_today_raw = $view_money + $watch_money;

            // ৪. টাকা যদি ০.০০০১ এর বেশি হয় তবেই প্রসেস করবে (Rounding issue সমাধান)
            if ($total_today_raw > 0) {
                
                // ডাটাবেজে সেভ করার জন্য ২ থেকে ৪ দশমিক ঘর পর্যন্ত রাখা ভালো
                $total_today = round($total_today_raw, 4);

                DB::transaction(function () use ($member, $total_today, $new_views, $new_watch_sec, $today, $current_views, $current_watch_time) {
                    
                    // ৫. আর্নিং টেবিল আপডেট বা ক্রিয়েট (Atomic Update)
                    Earning::updateOrCreate(
                        ['member_id' => $member->id, 'earning_date' => $today],
                        [
                            'amount' => DB::raw("amount + $total_today"),
                            'new_views' => DB::raw("new_views + $new_views"),
                            'new_watch_time' => DB::raw("new_watch_time + $new_watch_sec")
                        ]
                    );

                    // ৬. মেম্বার টেবিল আপডেট (Balance & Reference Counters)
                    $member->increment('balance', $total_today);
                    $member->increment('total_earned', $total_today);
                    
                    // ৭. ট্র্যাকিং পয়েন্ট আপডেট করা
                    $member->update([
                        'last_paid_views' => $current_views,
                        'last_paid_watch_time' => $current_watch_time
                    ]);

                    Log::info("Monetization: Income processed for Member #{$member->id}. Views: +{$new_views}, Amount: {$total_today}");
                });
            }
        }
    }

    // private function calculateIncome($member) {
    //     // আলাদা টেবিল থেকে বর্তমান ভিউ এবং ওয়াচ টাইম সংগ্রহ
    //     $current_views = DB::table('post_views')->where('member_id', $member->id)->count();
    //     $current_watch_time = DB::table('video_views')->where('member_id', $member->id)->sum('watch_time');
        
    //     $today = now()->format('Y-m-d');

    //     // নতুন কতটুকু ভিউ এবং সময় বাড়ল তা বের করা
    //     $new_views = $current_views - $member->last_paid_views;
    //     $new_watch_sec = $current_watch_time - $member->last_paid_watch_time;

    //     if ($new_views > 0 || $new_watch_sec > 0) {
    //         $view_money = ($new_views / 1000) * $this->view_rate;
    //         $watch_money = ($new_watch_sec / 3600) * $this->watch_hour_rate;
    //         $total_today = round($view_money + $watch_money, 2);

    //         if ($total_today > 0) {
    //             // আর্নিং টেবিল আপডেট
    //             $earning = Earning::where('member_id', $member->id)
    //                               ->where('earning_date', $today)
    //                               ->first();

    //             if ($earning) {
    //                 $earning->increment('amount', $total_today);
    //                 $earning->increment('new_views', $new_views);
    //                 $earning->increment('new_watch_time', $new_watch_sec);
    //             } else {
    //                 Earning::create([
    //                     'member_id' => $member->id,
    //                     'amount' => $total_today,
    //                     'new_views' => $new_views,
    //                     'new_watch_time' => $new_watch_sec,
    //                     'earning_date' => $today
    //                 ]);
    //             }

    //             // ব্যালেন্স আপডেট
    //             $member->increment('balance', $total_today);
    //             $member->increment('total_earned', $total_today);
                
    //             // পরবর্তী ক্যালকুলেশনের জন্য বর্তমান ভিউ ও সময় সেভ করে রাখা
    //             $member->update([
    //                 'last_paid_views' => $current_views,
    //                 'last_paid_watch_time' => $current_watch_time
    //             ]);

    //             Log::info("Income updated for Member: {$member->id}. Amount: {$total_today}");
    //         }
    //     }
    // }




}