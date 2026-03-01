<?php

use ChrisThompsonTLDR\LaravelRunPod\Console\PruneCommand;
use ChrisThompsonTLDR\LaravelRunPod\RunPodPodManager;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

uses(TestCase::class);

covers(PruneCommand::class);

it('outputs pod terminated when pruneIfInactive returns true', function () {
    $mockManager = \Mockery::mock(RunPodPodManager::class);
    $mockManager->shouldReceive('setStatePath')->once();
    $mockManager->shouldReceive('pruneIfInactive')->once()->andReturn(true);

    app()->instance(RunPodPodManager::class, $mockManager);

    $exitCode = Artisan::call('runpod:prune');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Pod terminated due to inactivity');
});

it('outputs pod still active when pruneIfInactive returns false', function () {
    $mockManager = \Mockery::mock(RunPodPodManager::class);
    $mockManager->shouldReceive('setStatePath')->once();
    $mockManager->shouldReceive('pruneIfInactive')->once()->andReturn(false);

    app()->instance(RunPodPodManager::class, $mockManager);

    $exitCode = Artisan::call('runpod:prune');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Pod still active or no pod to prune');
});

it('configures manager with instance pod config when instance given', function () {
    config([
        'runpod.instances' => [
            'pymupdf' => [
                'pod' => ['gpu_count' => 0, 'name' => 'test-pod'],
            ],
        ],
    ]);

    $mockManager = \Mockery::mock(RunPodPodManager::class);
    $mockManager->shouldReceive('setStatePath')->once();
    $mockManager->shouldReceive('configure')->once()->with(['gpu_count' => 0, 'name' => 'test-pod']);
    $mockManager->shouldReceive('pruneIfInactive')->once()->andReturn(false);

    app()->instance(RunPodPodManager::class, $mockManager);

    Artisan::call('runpod:prune', ['instance' => 'pymupdf']);
});

it('uses instance state_file when configured', function () {
    config([
        'runpod.instances' => [
            'custom' => [
                'state_file' => '/tmp/custom-state.json',
                'pod' => [],
            ],
        ],
    ]);

    $mockManager = \Mockery::mock(RunPodPodManager::class);
    $mockManager->shouldReceive('setStatePath')->with('/tmp/custom-state.json')->once();
    $mockManager->shouldReceive('configure')->once();
    $mockManager->shouldReceive('pruneIfInactive')->once()->andReturn(false);

    app()->instance(RunPodPodManager::class, $mockManager);

    Artisan::call('runpod:prune', ['instance' => 'custom']);
});

it('uses default state path when no instance', function () {
    config(['runpod.state_file' => storage_path('app/runpod-pod-state.json')]);

    $mockManager = \Mockery::mock(RunPodPodManager::class);
    $mockManager->shouldReceive('setStatePath')->with(storage_path('app/runpod-pod-state.json'))->once();
    $mockManager->shouldReceive('pruneIfInactive')->once()->andReturn(false);

    app()->instance(RunPodPodManager::class, $mockManager);

    Artisan::call('runpod:prune');
});
