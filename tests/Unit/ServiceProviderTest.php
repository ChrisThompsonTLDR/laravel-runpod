<?php

use ChrisThompsonTLDR\LaravelRunPod\Exceptions\RunPodApiKeyNotConfiguredException;
use ChrisThompsonTLDR\LaravelRunPod\RunPodClient;
use ChrisThompsonTLDR\LaravelRunPod\RunPodServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

covers(RunPodServiceProvider::class);

class ServiceProviderNoApiKeyTest extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [RunPodServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('runpod.api_key', null);
    }

    public function test_throws_when_api_key_null_on_first_api_call(): void
    {
        Http::preventStrayRequests();
        $client = $this->app->make(RunPodClient::class);
        $this->expectException(RunPodApiKeyNotConfiguredException::class);
        $client->listPods();
    }

    public function test_throws_when_api_key_empty_on_first_api_call(): void
    {
        Http::preventStrayRequests();
        $this->app['config']->set('runpod.api_key', '');
        $client = $this->app->make(RunPodClient::class);
        $this->expectException(RunPodApiKeyNotConfiguredException::class);
        $client->listPods();
    }
}
