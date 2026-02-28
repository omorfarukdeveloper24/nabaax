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

        // পরবর্তী রেফারারদের দ্রুত লোড করতে রিলেশনশিপ ব্যবহার
        $currentReferrer = $this->referrer_member->load('referrer'); 
        $level = 1;
        $maxLevel = 7; // ৭ জেনারেশন লিমিট

        while ($currentReferrer && $level <= $maxLevel) {
            try {
                DB::transaction(function () use ($currentReferrer, $level) {
                    
                    $amount = ($level === 1) ? $this->first_gen_bonus : $this->multi_gen_bonus;

                    if ($amount > 0) {
                        // ব্যালেন্স আপডেট
                        $currentReferrer->increment('balance', $amount);
                        $bonus_tnx = 'GEN' . $level . '-' . strtoupper(Str::random(10));

                        // কাস্টমার হিস্ট্রি (মেম্বার কমিশন পেলো)
                        CustomerPayHistory::create([
                            'member_id'    => $currentReferrer->id,
                            'payment_name' => "Generation Bonus (L-$level) from " . $this->member->username,
                            'tnx'          => $bonus_tnx,
                            'amount'       => $amount,
                            'balance'      => $currentReferrer->balance,
                            'method'       => 'Wallet',
                            'type'         => 'credit',
                        ]);

                        // অ্যাডমিন হিস্ট্রি (সিস্টেম থেকে কমিশন দেওয়া হলো)
                        AdminPayHistory::create([
                            'member_id'    => $currentReferrer->id,
                            'payment_name' => "Generation Bonus (L-$level) paid to " . $currentReferrer->username,
                            'tnx'          => $bonus_tnx,
                            'amount'       => $amount,
                            'balance'      => $currentReferrer->balance,
                            'method'       => 'Wallet',
                            'type'         => 'debit',
                        ]);

                        // নোটিফিকেশন
                        $this->sendFcmNotification(
                            $currentReferrer->id, 
                            "Commission Received! 💰", 
                            "You received $amount TK bonus from " . $this->member->username,
                            [
                                'bonus_amount' => (string)$amount,
                                'from_user'    => (string)$this->member->username,
                                'level'        => (string)$level
                            ],
                            'generation_bonus' // ৫ নম্বর প্যারামিটার (Type)
                        );
                    }
                });

                Log::info("Level $level Bonus sent to: " . $currentReferrer->username);

            } catch (\Exception $e) {
                Log::error("Failed at Level $level for {$currentReferrer->username}: " . $e->getMessage());
            }

            // ৭ লেভেল শেষ হলে লুপ ব্রেক করবে
            if ($level == $maxLevel) {
                Log::info("7th Generation reached. Stopping bonus distribution.");
                break;
            }

            // পরের রেফারারকে সেট করা
            $currentReferrer = $currentReferrer->referrer; 
            $level++;
        }

        Log::info("Partner Bonus Job Finished Successfully.");
    }



    


}
