<?php

namespace Chris\LaravelRunPod;

use Illuminate\Support\ServiceProvider;

class LaravelRunPodServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/runpod.php', 'runpod');
    }

    public function boot(): void
    {
        $this->registerRunPodDisk();
        $this->registerMacros();
        $this->registerCommands();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/runpod.php' => config_path('runpod.php'),
            ], 'laravel-runpod-config');
        }
    }

    protected function registerRunPodDisk(): void
    {
        $config = config('runpod.s3');

        if (! $config['key'] || ! $config['secret'] || ! $config['bucket']) {
            return;
        }

        config([
            'filesystems.disks.runpod' => [
                'driver' => 's3',
                'key' => $config['key'],
                'secret' => $config['secret'],
                'region' => $config['region'],
                'bucket' => $config['bucket'],
                'endpoint' => $config['endpoint'],
                'use_path_style_endpoint' => true,
                'throw' => false,
            ],
        ]);
    }

    protected function registerMacros(): void
    {
        Macros\StorageMacros::register();
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\SyncCommand::class,
            ]);
        }
    }
}
