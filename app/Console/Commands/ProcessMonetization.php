<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Member; 
use App\Services\MonetizationService; 
use Illuminate\Support\Facades\Log;

class ProcessMonetization extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    
    protected $signature = 'monetization:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(MonetizationService $service)
    {
        Log::info("Daily Monetization Cron Started...");

        // ১০০০ জন করে মেম্বার নিয়ে প্রসেস করবে যেন মেমোরি ওভারলোড না হয়
        Member::where('status', 1)->chunkById(1000, function ($members) use ($service) {
            foreach ($members as $member) {
                $service->processMember($member);
            }
        });

        Log::info("Daily Monetization Cron Finished Successfully.");
    }
}
