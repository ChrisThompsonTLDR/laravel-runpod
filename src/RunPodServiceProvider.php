<?php

namespace ChrisThompsonTLDR\LaravelRunPod;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;

class RunPodServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/runpod.php', 'runpod');

        $this->app->singleton(RunPodClient::class, function () {
            $apiKey = config('runpod.api_key') ?? '';

            return new RunPodClient(apiKey: $apiKey);
        });

        $this->app->singleton(RunPodGraphQLClient::class, function () {
            $apiKey = config('runpod.api_key') ?? '';

            return new RunPodGraphQLClient(apiKey: $apiKey);
        });

        $this->app->singleton(RunPodStatsWriter::class, fn () => new RunPodStatsWriter);

        $this->app->singleton(RunPodPodClient::class, function () {
            return new RunPodPodClient(
                client: $this->app->make(RunPodClient::class),
                graphql: $this->app->make(RunPodGraphQLClient::class)
            );
        });

        $this->app->singleton(RunPodPodManager::class, function () {
            return new RunPodPodManager(
                client: $this->app->make(RunPodPodClient::class),
                stateFilePath: storage_path('app/runpod-pod-state.json'),
                podConfig: [],
                statsWriter: $this->app->make(RunPodStatsWriter::class)
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

        $this->app->singleton(RunPodEndpointState::class, fn () => new RunPodEndpointState);
    }

    public function boot(): void
    {
        $this->registerRunPodDisk();
        $this->registerCommands();
        $this->registerSchedule();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/runpod.php' => config_path('runpod.php'),
            ], 'laravel-runpod-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/runpod'),
            ], 'laravel-runpod-dashboard');
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'runpod');

        if (class_exists(\Livewire\Livewire::class)) {
            $this->registerRunpodGate();
            $this->loadRoutesFrom(__DIR__.'/../routes/runpod.php');
        }
    }

    protected function registerRunpodGate(): void
    {
        Gate::define('viewRunpod', function (?object $user = null) {
            if (app()->environment('local')) {
                return true;
            }

            return $user !== null;
        });
    }

    /**
     * Register S3 disks for each instance that has s3 config.
     * RunPod network volumes are S3-compatible; bucket = network volume ID.
     *
     * RunPod S3 does not support x-amz-acl; the before_upload callback strips it.
     * Multipart upload is disabled (mup_threshold 500MB) because CreateMultipartUpload with ACL fails.
     */
    protected function registerRunPodDisk(): void
    {
        $instances = config('runpod.instances', []);

        foreach ($instances as $instanceConfig) {
            $remote = $instanceConfig['remote_disk'] ?? null;
            if (! $remote || ! ($remote['key'] ?? null) || ! ($remote['secret'] ?? null) || ! ($remote['bucket'] ?? null)) {
                continue;
            }

            $diskName = $remote['disk_name'] ?? 'runpod';

            config([
                "filesystems.disks.{$diskName}" => [
                    'driver' => 's3',
                    'key' => $remote['key'],
                    'secret' => $remote['secret'],
                    'region' => $remote['region'] ?? 'US-MD-1',
                    'bucket' => $remote['bucket'],
                    'endpoint' => $remote['endpoint'] ?? 'https://s3api-us-md-1.runpod.io',
                    'use_path_style_endpoint' => true,
                    'throw' => true,
                    'options' => [
                        'before_upload' => function ($command) {
                            $command->offsetUnset('ACL');
                        },
                        'mup_threshold' => 524288000,
                    ],
                ],
            ]);
        }
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\SyncCommand::class,
                Console\StartCommand::class,
                Console\DeployEndpointCommand::class,
                Console\ListCommand::class,
                Console\InspectCommand::class,
                Console\PruneCommand::class,
                Console\FlushCommand::class,
                Console\GuardrailsCommand::class,
                Console\StatsCommand::class,
                Console\DashboardCommand::class,
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
            $frequency = $config['prune_schedule'] ?? 'everyFiveMinutes';
            if (! in_array($frequency, $allowed, true)) {
                $frequency = 'everyFiveMinutes';
            }
            Schedule::command('runpod:prune', [$name])->{$frequency}();
        }

        // Fallback: if no instances, use legacy single prune
        if (empty($instances)) {
            Schedule::command('runpod:prune')->everyFiveMinutes();
        }

        // Guardrails: refresh usage cache on schedule
        $guardrailsSchedule = config('runpod.guardrails.cache_schedule', 'everyFifteenMinutes');
        if (config('runpod.guardrails.enabled', true) && in_array($guardrailsSchedule, $allowed, true)) {
            Schedule::command('runpod:guardrails')->{$guardrailsSchedule}();
        }

        // Stats: refresh dashboard stats file every 2 minutes
        Schedule::command('runpod:stats')->everyTwoMinutes();
    }
}
