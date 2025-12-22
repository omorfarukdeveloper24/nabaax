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
        if (class_exists(\Spatie\GoogleCloudStorage\GoogleCloudStorageServiceProvider::class)) {
            $this->app->register(\Spatie\GoogleCloudStorage\GoogleCloudStorageServiceProvider::class);
        }
    }

    public function boot()
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

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