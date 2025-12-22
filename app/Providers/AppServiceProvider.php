<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\GeneralSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // যেহেতু প্যাকেজটি অটো-ডিসকভার হচ্ছে, এখানে আলাদা করে রেজিস্ট্রেশনের প্রয়োজন নেই।
        // তবে আপনার আগের কোডটি সামঞ্জস্য বজায় রাখতে নিচে রাখা হলো।
        if (class_exists(\Spatie\GoogleCloudStorage\GoogleCloudStorageServiceProvider::class)) {
            $this->app->register(\Spatie\GoogleCloudStorage\GoogleCloudStorageServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // প্রোডাকশন এনভায়রনমেন্টে HTTPS ফোর্স করা
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // গ্লোবাল ভিউ কম্পোজার (General Setting)
        view()->composer('*', function ($view) {
            $generalsetting = Cache::remember('generalsetting', now()->addDays(7), function () {
                return GeneralSetting::where('status', 1)->first();
            });
            
            $view->with([
                'generalsetting' => $generalsetting,
            ]);
        });
    }
}