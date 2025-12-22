<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\GeneralSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // GCS ড্রাইভার রেজিস্ট্রেশন
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
    }
}