<?php

use ChrisThompsonTLDR\LaravelRunPod\RunPodPodClient;
use ChrisThompsonTLDR\LaravelRunPod\RunPodPodManager;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;
use Illuminate\Support\Facades\Http;

uses(TestCase::class);

covers(RunPodPodManager::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

it('returns existing pod when state has running pod', function () {
    $statePath = sys_get_temp_dir().'/runpod-test-'.uniqid().'.json';
    file_put_contents($statePath, json_encode(['pod_id' => 'pod-123', 'last_run_at' => now()->toIso8601String()]));

    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-123' => Http::response([
            'id' => 'pod-123',
            'desiredStatus' => 'RUNNING',
            'ports' => ['8000/http'],
        ], 200),
    ]);

    $client = app(RunPodPodClient::class);
    $manager = new RunPodPodManager($client, $statePath, []);
    $manager->setStatePath($statePath);

    $result = $manager->ensurePod();

    expect($result)->toBe([
        'pod_id' => 'pod-123',
        'url' => 'https://pod-123-8000.proxy.runpod.net',
    ]);

    @unlink($statePath);
});

it('returns null when createPod returns null', function () {
    $statePath = sys_get_temp_dir().'/runpod-test-'.uniqid().'.json';

    config(['runpod.pod.image_name' => null, 'runpod.guardrails.enabled' => false]);

    $client = app(RunPodPodClient::class);
    $manager = new RunPodPodManager($client, $statePath, []);

    $result = $manager->ensurePod();

    expect($result)->toBeNull();
});

it('getPodUrl returns null when no state', function () {
    $statePath = sys_get_temp_dir().'/runpod-test-'.uniqid().'.json';

    $mockClient = \Mockery::mock(RunPodPodClient::class);
    $mockClient->shouldNotReceive('getPublicUrl');

    $manager = new RunPodPodManager($mockClient, $statePath, []);

    expect($manager->getPodUrl())->toBeNull();
});

it('getPodUrl returns url when state has pod_id', function () {
    $statePath = sys_get_temp_dir().'/runpod-test-'.uniqid().'.json';
    file_put_contents($statePath, json_encode(['pod_id' => 'pod-123']));

    $mockClient = \Mockery::mock(RunPodPodClient::class);
    $mockClient->shouldReceive('getPublicUrl')->with('pod-123')->once()->andReturn('https://pod-123-8000.proxy.runpod.net');

    $manager = new RunPodPodManager($mockClient, $statePath, []);

    expect($manager->getPodUrl())->toBe('https://pod-123-8000.proxy.runpod.net');

    @unlink($statePath);
});

it('terminatePod returns true and clears state when no state', function () {
    $statePath = sys_get_temp_dir().'/runpod-test-'.uniqid().'.json';

    $mockClient = \Mockery::mock(RunPodPodClient::class);
    $mockClient->shouldNotReceive('terminatePod');

    $manager = new RunPodPodManager($mockClient, $statePath, []);

    expect($manager->terminatePod())->toBeTrue();
});

it('resolveStatePath returns absolute path as-is', function () {
    $manager = new RunPodPodManager(
        \Mockery::mock(RunPodPodClient::class),
        '/absolute/path.json',
        []
    );
    $method = (new \ReflectionClass(RunPodPodManager::class))->getMethod('resolveStatePath');
    $method->setAccessible(true);

    expect($method->invoke($manager))->toBe('/absolute/path.json');
});

it('terminatePod calls client and clears state when state exists', function () {
    $statePath = sys_get_temp_dir().'/runpod-test-'.uniqid().'.json';
    file_put_contents($statePath, json_encode(['pod_id' => 'pod-123']));

    $mockClient = \Mockery::mock(RunPodPodClient::class);
    $mockClient->shouldReceive('terminatePod')->with('pod-123')->once()->andReturn(true);

    $manager = new RunPodPodManager($mockClient, $statePath, []);

    expect($manager->terminatePod())->toBeTrue()
        ->and(file_exists($statePath))->toBeFalse();

    @unlink($statePath);
});

it('configure merges pod config', function () {
    config(['runpod.pod' => ['gpu_count' => 1]]);

    $manager = new RunPodPodManager(\Mockery::mock(RunPodPodClient::class), '/tmp/state.json', []);
    $manager->configure(['name' => 'custom-pod']);

    $ref = new \ReflectionClass($manager);
    $prop = $ref->getProperty('podConfig');
    $prop->setAccessible(true);
    $config = $prop->getValue($manager);

    expect($config)->toHaveKey('gpu_count', 1)
        ->and($config)->toHaveKey('name', 'custom-pod');
});

it('updateLastRunAt writes state when pod_id exists', function () {
    $statePath = sys_get_temp_dir().'/runpod-test-'.uniqid().'.json';
    $original = ['pod_id' => 'pod-123', 'last_run_at' => '2020-01-01T00:00:00Z'];
    file_put_contents($statePath, json_encode($original));

    $manager = new RunPodPodManager(\Mockery::mock(RunPodPodClient::class), $statePath, []);
    $manager->updateLastRunAt();

    $data = json_decode(file_get_contents($statePath), true);
    expect($data)->toHaveKey('pod_id', 'pod-123')
        ->and($data)->toHaveKey('last_run_at')
        ->and($data['last_run_at'])->not->toBe('2020-01-01T00:00:00Z');

    @unlink($statePath);
});

it('updateLastRunAt does nothing when no state', function () {
    $statePath = sys_get_temp_dir().'/runpod-test-'.uniqid().'.json';

    $manager = new RunPodPodManager(\Mockery::mock(RunPodPodClient::class), $statePath, []);
    $manager->updateLastRunAt();

    expect(file_exists($statePath))->toBeFalse();
});

it('pruneIfInactive returns false when last_run_at within threshold', function () {
    $statePath = sys_get_temp_dir().'/runpod-test-'.uniqid().'.json';
    file_put_contents($statePath, json_encode([
        'pod_id' => 'pod-123',
        'last_run_at' => now()->subMinutes(1)->toIso8601String(),
    ]));

    config(['runpod.pod.inactivity_minutes' => 2]);

    $mockClient = \Mockery::mock(RunPodPodClient::class);
    $mockClient->shouldNotReceive('terminatePod');

    $manager = new RunPodPodManager($mockClient, $statePath, []);

    expect($manager->pruneIfInactive())->toBeFalse();

    @unlink($statePath);
});

it('pruneIfInactive terminates when idle exceeds threshold', function () {
    $statePath = sys_get_temp_dir().'/runpod-test-'.uniqid().'.json';
    file_put_contents($statePath, json_encode([
        'pod_id' => 'pod-123',
        'last_run_at' => now()->subMinutes(5)->toIso8601String(),
    ]));

    config(['runpod.pod.inactivity_minutes' => 2]);

    $mockClient = \Mockery::mock(RunPodPodClient::class);
    $mockClient->shouldReceive('terminatePod')->with('pod-123')->once()->andReturn(true);

    $manager = new RunPodPodManager($mockClient, $statePath, []);

    expect($manager->pruneIfInactive())->toBeTrue()
        ->and(file_exists($statePath))->toBeFalse();
});
