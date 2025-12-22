<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\GeneralSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage; // এটি আগে থেকেই আছে
use League\Flysystem\Filesystem;
use Spatie\GoogleCloudStorage\GoogleCloudStorageAdapter;
use Google\Cloud\Storage\StorageClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // আপনার আগের রেজিস্ট্রেশন লজিক
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
        // ১. GCS ড্রাইভার ম্যানুয়ালি এক্সটেন্ড করা
        Storage::extend('gcs', function ($app, $config) {
            $storageClient = new StorageClient([
                'projectId' => $config['project_id'],
                'keyFilePath' => base_path($config['key_file']),
            ]);
            $bucket = $storageClient->bucket($config['bucket']);
            $pathPrefix = $config['path_prefix'] ?? '';
            
            $adapter = new GoogleCloudStorageAdapter($bucket, $pathPrefix);

            return new \League\Flysystem\FilesystemOperator\FilesystemAdapter(
                new \League\Flysystem\Filesystem($adapter),
                $adapter
            );
        });

        // ২. আপনার আগের প্রোডাকশন HTTPS লজিক
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // ৩. আপনার আগের ভিউ কম্পোজার লজিক
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