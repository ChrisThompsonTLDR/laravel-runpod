<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Tests;

use ChrisThompsonTLDR\LaravelRunPod\RunPodServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [RunPodServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('runpod.api_key', 'test-api-key-for-testing');
    }
}
