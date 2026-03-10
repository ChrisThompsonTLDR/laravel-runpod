<?php

use ChrisThompsonTLDR\LaravelRunPod\RunPod;
use ChrisThompsonTLDR\LaravelRunPod\RunPodEndpointState;
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

    $result = $runPod->instance('example');

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
                'remote_disk' => [
                    'prefix' => 'custom-prefix',
                ],
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
    $runPod->instance('example');

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
    $runPod->instance('example');

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
    $runPod->instance('example');

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
    $runPod->for('job-class')->instance('example');

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
    $runPod->instance('example')->start();

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
    $runPod = app(RunPod::class)->instance('custom');

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

it('resolveStatePath uses instance state_file when configured', function () {
    config([
        'runpod.instances' => [
            'custom' => [
                'state_file' => '/tmp/custom-state.json',
            ],
        ],
    ]);

    $runPod = app(RunPod::class)->instance('custom');
    $method = (new ReflectionClass(RunPod::class))->getMethod('resolveStatePath');
    $method->setAccessible(true);

    expect($method->invoke($runPod))->toBe('/tmp/custom-state.json');
});

it('resolveStatePath uses nickname for suffix when no instance', function () {
    config([
    ]);

    $runPod = app(RunPod::class)->for('my-job');
    $method = (new ReflectionClass(RunPod::class))->getMethod('resolveStatePath');
    $method->setAccessible(true);

    $path = $method->invoke($runPod);
    expect($path)->toContain('my-job');
    expect($path)->toContain('.json');
});

// =============================================================================
// mergedPodConfigForInstance (static, no app deps)
// =============================================================================

it('mergedPodConfigForInstance returns instance config with local flag', function () {
    config([
        'runpod.instances' => [
            'test-inst' => [
                'type' => 'pod',
                'inactivity_minutes' => 5,
            ],
        ],
    ]);

    $merged = RunPod::mergedPodConfigForInstance('test-inst');

    expect($merged['inactivity_minutes'])->toBe(5)
        ->and($merged['local'])->toBeFalse();
});

it('mergedPodConfigForInstance sets local true when type is local', function () {
    config([
        'runpod.instances' => [
            'local-inst' => [
                'type' => 'local',
                'local_url' => 'http://local:80',
            ],
        ],
    ]);

    $merged = RunPod::mergedPodConfigForInstance('local-inst');

    expect($merged['local'])->toBeTrue()
        ->and($merged['local_url'])->toBe('http://local:80');
});

it('mergedPodConfigForInstance returns empty when instance not found', function () {
    $merged = RunPod::mergedPodConfigForInstance('nonexistent');

    expect($merged)->toBeArray()
        ->and($merged['local'])->toBeFalse();
});

// =============================================================================
// parsePruneToMinutes - all schedule methods
// =============================================================================

it('parses all schedule methods to minutes', function (string $method, int $expected) {
    $runPod = new RunPod(
        Mockery::mock(RunPodPodManager::class),
        Mockery::mock(RunPodPodClient::class)
    );
    $ref = (new ReflectionClass(RunPod::class))->getMethod('parsePruneToMinutes');
    $ref->setAccessible(true);

    expect($ref->invoke($runPod, $method))->toBe($expected);
})->with([
    ['everyMinute', 1],
    ['everyTwoMinutes', 2],
    ['everyThreeMinutes', 3],
    ['everyFourMinutes', 4],
    ['everyFiveMinutes', 5],
    ['everyTenMinutes', 10],
    ['everyFifteenMinutes', 15],
    ['everyThirtyMinutes', 30],
    ['hourly', 60],
]);

it('returns 5 for unknown schedule in parsePruneToMinutes', function () {
    $runPod = new RunPod(
        Mockery::mock(RunPodPodManager::class),
        Mockery::mock(RunPodPodClient::class)
    );
    $ref = (new ReflectionClass(RunPod::class))->getMethod('parsePruneToMinutes');
    $ref->setAccessible(true);

    expect($ref->invoke($runPod, 'unknown'))->toBe(5);
});

// =============================================================================
// Serverless start
// =============================================================================

it('returns serverless endpoint from state when cached', function () {
    $mockManager = Mockery::mock(RunPodPodManager::class);
    $mockManager->shouldReceive('setNickname')->andReturnSelf();
    $mockManager->shouldReceive('setStatePath')->andReturnSelf();
    $mockManager->shouldReceive('updateLastRunAt')->twice(); // for() + ensureServerlessEndpoint

    $mockClient = Mockery::mock(RunPodPodClient::class);

    $mockEndpointState = Mockery::mock(RunPodEndpointState::class);
    $mockEndpointState->shouldReceive('read')->with('serverless-inst')->once()
        ->andReturn(['endpoint_id' => 'ep-123', 'url' => 'https://ep-123.runpod.ai']);

    app()->instance(RunPodEndpointState::class, $mockEndpointState);

    config([
        'runpod.instances' => [
            'serverless-inst' => ['type' => 'serverless', 'serverless' => []],
        ],
    ]);

    $runPod = new RunPod($mockManager, $mockClient);
    $runPod->for('test')->instance('serverless-inst');

    $result = $runPod->start();

    expect($result)->toHaveKeys(['url', 'endpoint_id'])
        ->and($result['url'])->toBe('https://ep-123.runpod.ai')
        ->and($result['endpoint_id'])->toBe('ep-123');
});

it('returns null from start() when serverless and no endpoint name', function () {
    $mockManager = Mockery::mock(RunPodPodManager::class);
    $mockClient = Mockery::mock(RunPodPodClient::class);
    $mockClient->shouldNotReceive('getServerlessEndpointByName');

    $mockEndpointState = Mockery::mock(RunPodEndpointState::class);
    $mockEndpointState->shouldReceive('read')->andReturn(null);

    app()->instance(RunPodEndpointState::class, $mockEndpointState);

    config([
        'runpod.instances' => [
            'serverless-inst' => ['type' => 'serverless', 'serverless' => []],
        ],
    ]);

    $runPod = new RunPod($mockManager, $mockClient);
    $runPod->instance('serverless-inst');

    expect($runPod->start())->toBeNull();
});
