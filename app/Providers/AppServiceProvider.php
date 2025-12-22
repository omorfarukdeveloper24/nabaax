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
        // প্যাকেজ অটো-ডিসকভার হলে এটি ফাঁকা রাখা যায়
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
   

    public function boot()
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        view()->composer('*', function ($view) {
            $generalsetting = Cache::remember('generalsetting', now()->addDays(7), function () {
                return \App\Models\GeneralSetting::where('status', 1)->first();
            });
            
            $view->with([
                'generalsetting' => $generalsetting,
            ]);
        });
    }
}