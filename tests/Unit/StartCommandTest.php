<?php

use ChrisThompsonTLDR\LaravelRunPod\RunPod;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

uses(TestCase::class);

covers(\ChrisThompsonTLDR\LaravelRunPod\Console\StartCommand::class);

it('returns failure when instance unknown', function () {
    config(['runpod.instances' => ['example' => []]]);

    $mockRunPod = \Mockery::mock(RunPod::class);
    app()->instance(RunPod::class, $mockRunPod);

    $exitCode = Artisan::call('runpod:start', ['instance' => 'unknown']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Unknown instance');
});

it('returns success when RunPod start returns pod with url', function () {
    config(['runpod.instances' => ['example' => ['type' => 'pod', 'image_name' => 'nginx']]]);

    $mockRunPod = \Mockery::mock(RunPod::class);
    $mockRunPod->shouldReceive('for')->with('runpod:start')->andReturnSelf();
    $mockRunPod->shouldReceive('instance')->with('example')->andReturnSelf();
    $mockRunPod->shouldReceive('start')->andReturn(['url' => 'https://pod-123.proxy.runpod.net']);

    app()->instance(RunPod::class, $mockRunPod);

    $exitCode = Artisan::call('runpod:start', ['instance' => 'example']);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Pod running');
});
