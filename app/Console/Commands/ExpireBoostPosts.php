<?php

namespace App\Console\Commands;
use App\Models\Post;
use App\Models\PostBoost;
use Carbon\Carbon;

use Illuminate\Console\Command;

class ExpireBoostPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:expire-boost';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire boosted posts when end_date (date only) is reached';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();

        $expiredBoosts = PostBoost::whereNotNull('end_date')
            ->whereDate('end_date', '<=', $today)
            ->get();

        foreach ($expiredBoosts as $boost) {
            Post::where('id', $boost->post_id)->update([
                'boost_status' => 0
            ]);
        }

        $this->info('Expired boosts updated successfully!');
    }
}
