<?php

use ChrisThompsonTLDR\LaravelRunPod\RunPodClient;
use ChrisThompsonTLDR\LaravelRunPod\RunPodPodClient;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;
use Illuminate\Support\Facades\Http;

uses(TestCase::class);

covers(RunPodPodClient::class);

beforeEach(function () {
    Http::preventStrayRequests();
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
