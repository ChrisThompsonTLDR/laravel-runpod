<?php

use ChrisThompsonTLDR\LaravelRunPod\RunPodServiceProvider;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;

uses(TestCase::class);

covers(RunPodServiceProvider::class);

it('registers runpod disk when instance has remote_disk config', function () {
    config([
        'runpod.api_key' => 'test-key',
        'runpod.instances' => [
            'example' => [
                'remote_disk' => [
                    'disk_name' => 'runpod',
                    'key' => 's3-key',
                    'secret' => 's3-secret',
                    'bucket' => 'my-bucket',
                    'region' => 'us-east-1',
                    'endpoint' => 'https://s3.example.com',
                ],
            ],
        ],
    ]);

    $provider = app()->getProvider(RunPodServiceProvider::class);
    $provider->boot();

    expect(config('filesystems.disks.runpod'))->toBeArray()
        ->and(config('filesystems.disks.runpod.driver'))->toBe('s3')
        ->and(config('filesystems.disks.runpod.bucket'))->toBe('my-bucket');
});

it('does not register runpod disk when instance remote_disk config incomplete', function () {
    config([
        'runpod.api_key' => 'test-key',
        'runpod.instances' => [
            'example' => [
                'remote_disk' => [
                    'disk_name' => 'runpod',
                    'key' => '',
                    'secret' => '',
                    'bucket' => '',
                    'region' => 'us-east-1',
                    'endpoint' => 'https://s3.example.com',
                ],
            ],
        ],
    ]);

    $provider = app()->getProvider(RunPodServiceProvider::class);
    $provider->boot();

    expect(config('filesystems.disks.runpod'))->toBeNull();
});
