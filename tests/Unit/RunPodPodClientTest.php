<?php

use ChrisThompsonTLDR\LaravelRunPod\RunPodClient;
use ChrisThompsonTLDR\LaravelRunPod\RunPodGraphQLClient;
use ChrisThompsonTLDR\LaravelRunPod\RunPodPodClient;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;
use Illuminate\Support\Facades\Http;

uses(TestCase::class);

covers(RunPodPodClient::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

it('delegates getPod to client', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-123' => Http::response(['id' => 'pod-123', 'desiredStatus' => 'RUNNING'], 200),
    ]);

    $podClient = new RunPodPodClient(new RunPodClient('test-key'));

    expect($podClient->getPod('pod-123'))->toBe(['id' => 'pod-123', 'desiredStatus' => 'RUNNING']);
});

it('returns telemetry from getPodTelemetry when graphql provided', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response([], 200),
        'https://api.runpod.io/graphql' => Http::response([
            'data' => ['pod' => ['latestTelemetry' => ['cpuUtilization' => 50]]],
        ], 200),
    ]);

    $podClient = new RunPodPodClient(
        new RunPodClient('test-key'),
        new RunPodGraphQLClient('test-key')
    );

    expect($podClient->getPodTelemetry('pod-123'))->toBe(['cpuUtilization' => 50]);
});

it('returns null from getPodTelemetry when graphql not provided', function () {
    $podClient = new RunPodPodClient(new RunPodClient('test-key'));

    expect($podClient->getPodTelemetry('pod-123'))->toBeNull();
});

it('returns endpoint from getServerlessEndpointByName when name matches', function () {
    Http::fake([
        'https://rest.runpod.io/v1/endpoints*' => Http::response([
            ['id' => 'ep-abc', 'name' => 'my-endpoint'],
        ], 200),
    ]);

    $podClient = new RunPodPodClient(new RunPodClient('test-key'));

    expect($podClient->getServerlessEndpointByName('my-endpoint'))->toBe([
        'url' => 'https://api.runpod.ai/v2/ep-abc/runsync',
        'endpoint_id' => 'ep-abc',
    ]);
});

it('getServerlessEndpointByName matches prefix with space', function () {
    Http::fake([
        'https://rest.runpod.io/v1/endpoints*' => Http::response([
            ['id' => 'ep-xyz', 'name' => 'my-endpoint (active)'],
        ], 200),
    ]);

    $podClient = new RunPodPodClient(new RunPodClient('test-key'));

    expect($podClient->getServerlessEndpointByName('my-endpoint'))->toBe([
        'url' => 'https://api.runpod.ai/v2/ep-xyz/runsync',
        'endpoint_id' => 'ep-xyz',
    ]);
});

it('returns null from getServerlessEndpointByName when no match', function () {
    Http::fake([
        'https://rest.runpod.io/v1/endpoints*' => Http::response([
            ['id' => 'ep-abc', 'name' => 'other-endpoint'],
        ], 200),
    ]);

    $podClient = new RunPodPodClient(new RunPodClient('test-key'));

    expect($podClient->getServerlessEndpointByName('nonexistent'))->toBeNull();
});

it('returns tcp url with publicIp and portMappings', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-123' => Http::response([
            'ports' => ['22/tcp'],
            'publicIp' => '1.2.3.4',
            'portMappings' => ['22' => 12345],
        ], 200),
    ]);

    $podClient = new RunPodPodClient(new RunPodClient('test-key'));

    expect($podClient->getPublicUrl('pod-123', 22))->toBe('http://1.2.3.4:12345');
});

it('uses portMappings fallback when ports empty', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-123' => Http::response([
            'ports' => [],
            'publicIp' => '1.2.3.4',
            'portMappings' => ['8000' => 54321],
        ], 200),
    ]);

    $podClient = new RunPodPodClient(new RunPodClient('test-key'));

    expect($podClient->getPublicUrl('pod-123', 8000))->toBe('http://1.2.3.4:54321');
});

it('returns fallback url when pod is null', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-123' => Http::response([], 404),
    ]);

    $podClient = new RunPodPodClient(new RunPodClient('test-key'));

    expect($podClient->getPublicUrl('pod-123'))->toBe('https://pod-123-8000.proxy.runpod.net');
});

it('returns url with custom port when pod is null', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-123' => Http::response([], 404),
    ]);

    $podClient = new RunPodPodClient(new RunPodClient('test-key'));

    expect($podClient->getPublicUrl('pod-123', 9000))->toBe('https://pod-123-9000.proxy.runpod.net');
});

it('parses ports array and returns url with first http port', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-123' => Http::response([
            'ports' => ['8000/http', '22/tcp'],
        ], 200),
    ]);

    $podClient = new RunPodPodClient(new RunPodClient('test-key'));

    expect($podClient->getPublicUrl('pod-123'))->toBe('https://pod-123-8000.proxy.runpod.net');
});

it('uses runtime.ports when ports not at top level', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-123' => Http::response([
            'runtime' => ['ports' => ['8080/http']],
        ], 200),
    ]);

    $podClient = new RunPodPodClient(new RunPodClient('test-key'));

    expect($podClient->getPublicUrl('pod-123'))->toBe('https://pod-123-8080.proxy.runpod.net');
});

it('falls back to privatePort when no http port in pod', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-123' => Http::response([
            'ports' => ['22/tcp'],
        ], 200),
    ]);

    $podClient = new RunPodPodClient(new RunPodClient('test-key'));

    expect($podClient->getPublicUrl('pod-123', 9000))->toBe('https://pod-123-9000.proxy.runpod.net');
});

it('aggregates getMyself from list endpoints', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response([['id' => 'pod-1']], 200),
        'https://rest.runpod.io/v1/endpoints*' => Http::response([['id' => 'ep-1']], 200),
        'https://rest.runpod.io/v1/networkvolumes*' => Http::response([['id' => 'vol-1']], 200),
    ]);

    $podClient = new RunPodPodClient(new RunPodClient('test-key'));
    $result = $podClient->getMyself();

    expect($result)->toBe([
        'pods' => [['id' => 'pod-1']],
        'endpoints' => [['id' => 'ep-1']],
        'networkVolumes' => [['id' => 'vol-1']],
    ]);
});

it('returns true from stopPod when client returns array', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-123/stop' => Http::response(['status' => 'STOPPED'], 200),
    ]);

    $podClient = new RunPodPodClient(new RunPodClient('test-key'));

    expect($podClient->stopPod('pod-123'))->toBeTrue();
});

it('returns false from stopPod when client returns null', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-123/stop' => Http::response([], 500),
    ]);

    $podClient = new RunPodPodClient(new RunPodClient('test-key'));

    expect($podClient->stopPod('pod-123'))->toBeFalse();
});

it('delegates createPod to client', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods' => Http::response(['id' => 'pod-new'], 201),
    ]);

    $podClient = new RunPodPodClient(new RunPodClient('test-key'));
    $result = $podClient->createPod(['imageName' => 'runpod/pytorch']);

    expect($result)->toBe(['id' => 'pod-new']);
});

it('delegates terminatePod to client deletePod', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-123' => Http::response(null, 204),
    ]);

    $podClient = new RunPodPodClient(new RunPodClient('test-key'));

    expect($podClient->terminatePod('pod-123'))->toBeTrue();
});
