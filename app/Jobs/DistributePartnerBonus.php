<?php

namespace App\Jobs;

use App\Models\Member;
use App\Models\CustomerPayHistory;
use App\Models\AdminPayHistory;
use App\Traits\NotificationTrait; // ট্রেইট ইমপোর্ট করুন
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log; // লগ ইমপোর্ট করুন
use DB;

class DistributePartnerBonus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NotificationTrait; // ট্রেইট যুক্ত করুন

    protected $member;
    protected $referrer_member;
    protected $first_gen_bonus;
    protected $multi_gen_bonus;

    public function __construct($member, $referrer_member, $first_gen_bonus, $multi_gen_bonus)
    {
        $this->member = $member;
        $this->referrer_member = $referrer_member;
        $this->first_gen_bonus = $first_gen_bonus;
        $this->multi_gen_bonus = $multi_gen_bonus;
    }

    public function handle()
    {
        Log::info("Partner Bonus Job Started for: " . $this->member->username);

        // Eager loading ব্যবহার করে প্রথম রেফারারকে লোড করুন
        $currentReferrer = $this->referrer_member->load(['referrer' => function($query) {
            $query->select('id', 'name', 'username', 'balance', 'referrer_id');
        }]);
        $level = 1;

        while ($currentReferrer && $level <= 100) {
            try {
                // ট্রানজেকশন প্রতিটি মেম্বারের জন্য আলাদাভাবে নেওয়া হয়েছে (যাতে একজনের সমস্যায় সবারটা আটকে না যায়)
                DB::transaction(function () use ($currentReferrer, $level) {
                    
                    $amount = ($level === 1) ? $this->first_gen_bonus : $this->multi_gen_bonus;

                    if ($amount <= 0) return;

                    // ব্যালেন্স আপডেট
                    $currentReferrer->increment('balance', $amount);

                    // পেমেন্ট হিস্ট্রি
                    CustomerPayHistory::create([
                        'member_id'    => $currentReferrer->id,
                        'payment_name' => "Generation Bonus (L-$level) from " . $this->member->username,
                        'tnx'          => 'GEN' . $level . '-' . strtoupper(Str::random(10)),
                        'amount'       => $amount,
                        'balance'      => $currentReferrer->balance,
                        'method'       => 'Wallet',
                        'type'         => 'credit',
                    ]);

                    // নোটিফিকেশন
                    $this->sendFcmNotification(
                        $currentReferrer->id, 
                        "Commission Received! 💰", 
                        "You received $amount TK bonus from " . $this->member->username
                    );
                });

                Log::info("Level $level Bonus sent to: " . $currentReferrer->username);

            } catch (\Exception $e) {
                // যদি নির্দিষ্ট এই মেম্বারের ক্ষেত্রে কোনো এরর হয়, তবে সেটি লগে যাবে
                Log::error("Failed to give bonus to Level $level (User: {$currentReferrer->username}): " . $e->getMessage());
                
                // এখানে আপনি চাইলে একটি 'Error Log' টেবিলে ডাটা সেভ করতে পারেন ভবিষ্যতে চেক করার জন্য
            }

            // রিলেশনশিপ ব্যবহার করে পরের রেফারারকে সেট করা (বারবার DB::find না করে)
            $currentReferrer = $currentReferrer->referrer; 
            $level++;
        }

        Log::info("Partner Bonus Job Processed up to Level " . ($level - 1));
    }


}
