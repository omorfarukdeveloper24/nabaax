<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\GeneralSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        // এখানে কিছু করার দরকার নেই
    }

    public function boot()
    {
        // প্রোডাকশনে HTTPS ফোর্স করা
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // গ্লোবাল ভিউ কম্পোজার
        view()->composer('*', function ($view) {
            $generalsetting = Cache::remember('generalsetting', now()->addDays(7), function () {
                return GeneralSetting::where('status', 1)->first();
            });
            
            $view->with([
                'generalsetting' => $generalsetting,
            ]);
        });
        
        // নোট: এখানে কোনো Storage::extend রাখার দরকার নেই। 
        // প্যাকেজটি অটোমেটিক 'gcs' ড্রাইভার রেজিস্টার করবে।
    }
}