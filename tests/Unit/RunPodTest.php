<?php

use ChrisThompsonTLDR\LaravelRunPod\RunPod;
use ChrisThompsonTLDR\LaravelRunPod\RunPodFileManager;
use ChrisThompsonTLDR\LaravelRunPod\RunPodPodClient;
use ChrisThompsonTLDR\LaravelRunPod\RunPodPodManager;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

uses(TestCase::class);

covers(RunPod::class);

beforeEach(function () {
    Storage::fake('runpod');
});

it('can resolve the RunPod class', function () {
    expect(class_exists(RunPod::class))->toBeTrue();
});

it('chains for() and returns self', function () {
    $runPod = app(RunPod::class);

    $result = $runPod->for('test-nickname');

    expect($result)->toBe($runPod);
});

it('chains instance() and returns self', function () {
    $runPod = app(RunPod::class);

    $result = $runPod->instance('pymupdf');

    expect($result)->toBe($runPod);
});

it('returns RunPodFileManager from disk()', function () {
    $runPod = app(RunPod::class);

    $manager = $runPod->disk('runpod');

    expect($manager)->toBeInstanceOf(RunPodFileManager::class);
});

it('uses runpod.disk config when disk() called with no argument', function () {
    config(['runpod.disk' => 'runpod']);

    $runPod = app(RunPod::class);
    $manager = $runPod->disk();

    expect($manager)->toBeInstanceOf(RunPodFileManager::class);
});

it('uses instance config for disk load_path and remote_prefix when instance is set', function () {
    config([
        'runpod.instances' => [
            'custom' => [
                'load_path' => '/custom/load',
                'remote_prefix' => 'custom-prefix',
            ],
        ],
    ]);

    $runPod = app(RunPod::class)->instance('custom');
    $manager = $runPod->disk('runpod');

    expect($manager)->toBeInstanceOf(RunPodFileManager::class);
});

it('returns null from start() when podManager ensurePod returns null', function () {
    $mockManager = Mockery::mock(RunPodPodManager::class);
    $mockManager->shouldReceive('configure')->once();
    $mockManager->shouldReceive('setStatePath')->once();
    $mockManager->shouldReceive('setInstanceName')->once();
    $mockManager->shouldReceive('ensurePod')->once()->andReturn(null);

    $mockClient = Mockery::mock(RunPodPodClient::class);
    $runPod = new RunPod($mockManager, $mockClient);
    $runPod->instance('pymupdf');

    $result = $runPod->start();

    expect($result)->toBeNull();
});

it('returns null from start() when pod has no url', function () {
    $mockManager = Mockery::mock(RunPodPodManager::class);
    $mockManager->shouldReceive('configure')->once();
    $mockManager->shouldReceive('setStatePath')->once();
    $mockManager->shouldReceive('setInstanceName')->once();
    $mockManager->shouldReceive('ensurePod')->once()->andReturn(['pod_id' => 'abc', 'url' => null]);

    $mockClient = Mockery::mock(RunPodPodClient::class);
    $runPod = new RunPod($mockManager, $mockClient);
    $runPod->instance('pymupdf');

    $result = $runPod->start();

    expect($result)->toBeNull();
});

it('returns pod from start() when ensurePod succeeds', function () {
    $mockManager = Mockery::mock(RunPodPodManager::class);
    $mockManager->shouldReceive('configure')->once();
    $mockManager->shouldReceive('setStatePath')->once();
    $mockManager->shouldReceive('setInstanceName')->once();
    $mockManager->shouldReceive('ensurePod')->once()->andReturn([
        'pod_id' => 'pod-123',
        'url' => 'https://pod-123.runpod.io',
    ]);

    $mockClient = Mockery::mock(RunPodPodClient::class);
    $runPod = new RunPod($mockManager, $mockClient);
    $runPod->instance('pymupdf');

    $result = $runPod->start();

    expect($result)->toBe([
        'pod_id' => 'pod-123',
        'url' => 'https://pod-123.runpod.io',
    ]);
});

it('updates lastRunAt when nickname is set in start()', function () {
    $mockManager = Mockery::mock(RunPodPodManager::class);
    $mockManager->shouldReceive('setNickname')->with('job-class')->once();
    $mockManager->shouldReceive('setStatePath')->twice(); // for() + start()
    $mockManager->shouldReceive('setInstanceName')->once();
    $mockManager->shouldReceive('updateLastRunAt')->times(3); // for() + start() before and after ensurePod
    $mockManager->shouldReceive('configure')->once();
    $mockManager->shouldReceive('ensurePod')->once()->andReturn([
        'pod_id' => 'pod-123',
        'url' => 'https://pod-123.runpod.io',
    ]);

    $mockClient = Mockery::mock(RunPodPodClient::class);
    $runPod = new RunPod($mockManager, $mockClient);
    $runPod->for('job-class')->instance('pymupdf');

    $runPod->start();
});

it('returns url from podResult after start()', function () {
    $mockManager = Mockery::mock(RunPodPodManager::class);
    $mockManager->shouldReceive('configure')->once();
    $mockManager->shouldReceive('setStatePath')->once();
    $mockManager->shouldReceive('setInstanceName')->once();
    $mockManager->shouldReceive('ensurePod')->once()->andReturn([
        'pod_id' => 'pod-123',
        'url' => 'https://pod-123.runpod.io',
    ]);

    $mockClient = Mockery::mock(RunPodPodClient::class);
    $runPod = new RunPod($mockManager, $mockClient);
    $runPod->instance('pymupdf')->start();

    expect($runPod->url())->toBe('https://pod-123.runpod.io');
});

it('returns url from podManager when podResult has no url', function () {
    $mockManager = Mockery::mock(RunPodPodManager::class);
    $mockManager->shouldReceive('getPodUrl')->once()->andReturn('https://fallback.runpod.io');

    $mockClient = Mockery::mock(RunPodPodClient::class);
    $runPod = new RunPod($mockManager, $mockClient);

    expect($runPod->url())->toBe('https://fallback.runpod.io');
});

it('returns null from url() when no pod started and podManager returns null', function () {
    $mockManager = Mockery::mock(RunPodPodManager::class);
    $mockManager->shouldReceive('getPodUrl')->once()->andReturn(null);

    $mockClient = Mockery::mock(RunPodPodClient::class);
    $runPod = new RunPod($mockManager, $mockClient);

    expect($runPod->url())->toBeNull();
});

it('chains startWithPrune() and returns self', function () {
    $runPod = app(RunPod::class)->instance('pymupdf');

    $result = $runPod->startWithPrune('everyFiveMinutes');

    expect($result)->toBe($runPod);
});

it('handles serverless instance in startWithPrune', function () {
    config([
        'runpod.instances' => [
            'serverless-inst' => [
                'type' => 'serverless',
                'serverless' => [],
            ],
        ],
    ]);

    $runPod = app(RunPod::class)->instance('serverless-inst');
    $result = $runPod->startWithPrune('everyTenMinutes');

    expect($result)->toBe($runPod);
});

it('parses everyMinute to 1 minute', function () {
    $runPod = app(RunPod::class);
    $method = (new ReflectionClass(RunPod::class))->getMethod('parsePruneToMinutes');
    $method->setAccessible(true);

    expect($method->invoke($runPod, 'everyMinute'))->toBe(1);
});

it('parses everyFiveMinutes to 5 minutes', function () {
    $runPod = app(RunPod::class);
    $method = (new ReflectionClass(RunPod::class))->getMethod('parsePruneToMinutes');
    $method->setAccessible(true);

    expect($method->invoke($runPod, 'everyFiveMinutes'))->toBe(5);
});

it('parses hourly to 60 minutes', function () {
    $runPod = app(RunPod::class);
    $method = (new ReflectionClass(RunPod::class))->getMethod('parsePruneToMinutes');
    $method->setAccessible(true);

    expect($method->invoke($runPod, 'hourly'))->toBe(60);
});

it('returns 5 for unknown schedule method', function () {
    $runPod = app(RunPod::class);
    $method = (new ReflectionClass(RunPod::class))->getMethod('parsePruneToMinutes');
    $method->setAccessible(true);

    expect($method->invoke($runPod, 'unknown'))->toBe(5);
});

it('resolveStatePath uses instance state_file when configured', function () {
    config([
        'runpod.instances' => [
            'pymupdf' => [
                'state_file' => '/tmp/custom-state.json',
            ],
        ],
    ]);

    $runPod = app(RunPod::class)->instance('pymupdf');
    $method = (new ReflectionClass(RunPod::class))->getMethod('resolveStatePath');
    $method->setAccessible(true);

    expect($method->invoke($runPod))->toBe('/tmp/custom-state.json');
});

it('resolveStatePath uses nickname for suffix when no instance', function () {
    config([
        'runpod.state_file' => storage_path('app/runpod-pod-state.json'),
    ]);

    $runPod = app(RunPod::class)->for('my-job');
    $method = (new ReflectionClass(RunPod::class))->getMethod('resolveStatePath');
    $method->setAccessible(true);

    $path = $method->invoke($runPod);
    expect($path)->toContain('my-job');
    expect($path)->toContain('.json');
});
