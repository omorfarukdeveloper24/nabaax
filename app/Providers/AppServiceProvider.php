<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\GeneralSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use Google\Cloud\Storage\StorageClient;
use Spatie\GoogleCloudStorage\GoogleCloudStorageAdapter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // GCS ড্রাইভার সাপোর্ট যোগ করা
        Storage::extend('gcs', function ($app, $config) {
            $storageClient = new StorageClient([
                'projectId' => $config['project_id'],
                'keyFilePath' => $config['key_file'],
            ]);

            $bucket = $storageClient->bucket($config['bucket']);
            $adapter = new GoogleCloudStorageAdapter($bucket, $config['path_prefix'] ?? '');

            return new \Illuminate\Filesystem\FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });

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