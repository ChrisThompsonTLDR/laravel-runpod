<?php

namespace ChrisThompsonTLDR\LaravelRunPod;

use ChrisThompsonTLDR\LaravelRunPod\Exceptions\RunPodApiKeyNotConfiguredException;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;

class LaravelRunPodServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/runpod.php', 'runpod');

        $this->app->singleton(RunPodClient::class, function () {
            $apiKey = config('runpod.api_key');

            if ($apiKey === null || $apiKey === '') {
                throw new RunPodApiKeyNotConfiguredException;
            }

            return new RunPodClient(apiKey: $apiKey);
        });

        $this->app->singleton(RunPodPodClient::class, function () {
            return new RunPodPodClient(
                client: $this->app->make(RunPodClient::class)
            );
        });

        $this->app->singleton(RunPodPodManager::class, function () {
            return new RunPodPodManager(
                client: $this->app->make(RunPodPodClient::class),
                stateFilePath: config('runpod.state_file', storage_path('app/runpod-pod-state.json')),
                podConfig: config('runpod.pod', [])
            );
        });

        $this->app->singleton(RunPod::class, function () {
            return new RunPod(
                podManager: $this->app->make(RunPodPodManager::class),
                client: $this->app->make(RunPodPodClient::class)
            );
        });

        $this->app->singleton(RunPodGuardrails::class, function () {
            return new RunPodGuardrails(
                client: $this->app->make(RunPodClient::class)
            );
        });
    }

    public function boot(): void
    {
        $this->registerRunPodDisk();
        $this->registerMacros();
        $this->registerCommands();
        $this->registerSchedule();

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
                Console\StartCommand::class,
                Console\PruneCommand::class,
                Console\GuardrailsCommand::class,
            ]);
        }
    }

    protected function registerSchedule(): void
    {
        $instances = config('runpod.instances', []);
        $allowed = [
            'everyMinute', 'everyTwoMinutes', 'everyThreeMinutes', 'everyFourMinutes',
            'everyFiveMinutes', 'everyTenMinutes', 'everyFifteenMinutes', 'everyThirtyMinutes',
            'hourly',
        ];

        foreach ($instances as $name => $config) {
            if (($config['type'] ?? 'pod') !== 'pod') {
                continue;
            }
            $frequency = $config['prune_schedule'] ?? config('runpod.prune_schedule', 'everyFiveMinutes');
            if (! in_array($frequency, $allowed, true)) {
                $frequency = 'everyFiveMinutes';
            }
            Schedule::command('runpod:prune', ['instance' => $name])->{$frequency}();
        }

        // Fallback: if no instances, use legacy single prune
        if (empty($instances)) {
            $frequency = config('runpod.prune_schedule', 'everyFiveMinutes');
            if (! in_array($frequency, $allowed, true)) {
                $frequency = 'everyFiveMinutes';
            }
            Schedule::command('runpod:prune')->{$frequency}();
        }

        // Guardrails: refresh usage cache on schedule
        $guardrailsSchedule = config('runpod.guardrails.cache_schedule', 'everyFifteenMinutes');
        if (config('runpod.guardrails.enabled', true) && in_array($guardrailsSchedule, $allowed, true)) {
            Schedule::command('runpod:guardrails')->{$guardrailsSchedule}();
        }
    }
}
