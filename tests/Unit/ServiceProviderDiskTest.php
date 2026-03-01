<?php

use ChrisThompsonTLDR\LaravelRunPod\LaravelRunPodServiceProvider;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;

uses(TestCase::class);

covers(LaravelRunPodServiceProvider::class);

it('registers runpod disk when s3 config present', function () {
    config([
        'runpod.api_key' => 'test-key',
        'runpod.s3' => [
            'key' => 's3-key',
            'secret' => 's3-secret',
            'bucket' => 'my-bucket',
            'region' => 'us-east-1',
            'endpoint' => 'https://s3.example.com',
        ],
    ]);

    $provider = app()->getProvider(LaravelRunPodServiceProvider::class);
    $provider->boot();

    expect(config('filesystems.disks.runpod'))->toBeArray()
        ->and(config('filesystems.disks.runpod.driver'))->toBe('s3')
        ->and(config('filesystems.disks.runpod.bucket'))->toBe('my-bucket');
});

it('does not register runpod disk when s3 config incomplete', function () {
    config([
        'runpod.api_key' => 'test-key',
        'runpod.s3' => [
            'key' => '',
            'secret' => '',
            'bucket' => '',
            'region' => 'us-east-1',
            'endpoint' => 'https://s3.example.com',
        ],
    ]);

    $provider = app()->getProvider(LaravelRunPodServiceProvider::class);
    $provider->boot();

    expect(config('filesystems.disks.runpod'))->toBeNull();
});
