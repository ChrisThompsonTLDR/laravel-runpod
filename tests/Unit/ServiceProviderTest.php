<?php

use ChrisThompsonTLDR\LaravelRunPod\Exceptions\RunPodApiKeyNotConfiguredException;
use ChrisThompsonTLDR\LaravelRunPod\LaravelRunPodServiceProvider;
use ChrisThompsonTLDR\LaravelRunPod\RunPodClient;
use Orchestra\Testbench\TestCase as Orchestra;

covers(LaravelRunPodServiceProvider::class);

class ServiceProviderNoApiKeyTest extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LaravelRunPodServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('runpod.api_key', null);
    }

    public function test_throws_when_api_key_null(): void
    {
        $this->expectException(RunPodApiKeyNotConfiguredException::class);
        $this->app->make(RunPodClient::class);
    }

    public function test_throws_when_api_key_empty(): void
    {
        $this->app['config']->set('runpod.api_key', '');
        $this->expectException(RunPodApiKeyNotConfiguredException::class);
        $this->app->make(RunPodClient::class);
    }
}
