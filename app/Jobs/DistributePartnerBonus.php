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

        $currentReferrer = $this->referrer_member; 
        $level = 1;

        try {
            while ($currentReferrer && $level <= 100) {
                $amount = ($level === 1) ? $this->first_gen_bonus : $this->multi_gen_bonus;

                if ($amount > 0) {
                    $currentReferrer->increment('balance', $amount);
                    $bonus_tnx = 'GEN' . $level . '-' . strtoupper(Str::random(10));

                    CustomerPayHistory::create([
                        'member_id'    => $currentReferrer->id,
                        'payment_name' => "Generation Bonus (L-$level) from " . $this->member->username,
                        'tnx'          => $bonus_tnx,
                        'amount'       => $amount,
                        'balance'      => $currentReferrer->balance,
                        'method'       => 'Wallet',
                        'type'         => 'credit',
                    ]);

                    // নোটিফিকেশন পাঠানো
                    $this->sendFcmNotification(
                        $currentReferrer->id, 
                        "Commission Received! 💰", 
                        "You received $amount TK as level $level bonus from " . $this->member->username
                    );

                    Log::info("Bonus sent to Level $level: " . $currentReferrer->username);
                }

                if ($currentReferrer->referrer_id) {
                    $currentReferrer = Member::find($currentReferrer->referrer_id);
                } else {
                    $currentReferrer = null; 
                }
                $level++;
            }
            Log::info("Partner Bonus Job Finished Successfully.");
        } catch (\Exception $e) {
            Log::error("Error in DistributePartnerBonus Job: " . $e->getMessage());
            throw $e; // কিউ ফেইলড জব হিসেবে দেখানোর জন্য
        }
    }


}
