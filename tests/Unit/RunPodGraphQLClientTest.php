<?php

use ChrisThompsonTLDR\LaravelRunPod\Exceptions\RunPodApiKeyNotConfiguredException;
use ChrisThompsonTLDR\LaravelRunPod\RunPodGraphQLClient;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;
use Illuminate\Support\Facades\Http;

uses(TestCase::class);

covers(RunPodGraphQLClient::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

it('returns telemetry when getPodTelemetry succeeds', function () {
    $telemetry = [
        'time' => '2024-01-01T00:00:00Z',
        'state' => 'RUNNING',
        'cpuUtilization' => 42.5,
        'memoryUtilization' => 60.0,
        'averageGpuMetrics' => [['percentUtilization' => 80]],
        'individualGpuMetrics' => [],
    ];
    Http::fake([
        'https://api.runpod.io/graphql' => Http::response([
            'data' => ['pod' => ['latestTelemetry' => $telemetry]],
        ], 200),
    ]);

    $client = new RunPodGraphQLClient('test-key');
    $result = $client->getPodTelemetry('pod-123');

    expect($result)->toBeArray()
        ->and($result['time'])->toBe('2024-01-01T00:00:00Z')
        ->and($result['state'])->toBe('RUNNING')
        ->and($result['cpuUtilization'])->toBe(42.5)
        ->and($result['averageGpuMetrics'])->toHaveCount(1);
});

it('returns null when getPodTelemetry request fails', function () {
    Http::fake([
        'https://api.runpod.io/graphql' => Http::response([], 500),
    ]);

    $client = new RunPodGraphQLClient('test-key');
    $result = $client->getPodTelemetry('pod-123');

    expect($result)->toBeNull();
});

it('returns null when pod not found', function () {
    Http::fake([
        'https://api.runpod.io/graphql' => Http::response(['data' => ['pod' => null]], 200),
    ]);

    $client = new RunPodGraphQLClient('test-key');
    $result = $client->getPodTelemetry('pod-123');

    expect($result)->toBeNull();
});

it('returns null when latestTelemetry is null', function () {
    Http::fake([
        'https://api.runpod.io/graphql' => Http::response([
            'data' => ['pod' => ['id' => 'pod-123', 'latestTelemetry' => null]],
        ], 200),
    ]);

    $client = new RunPodGraphQLClient('test-key');
    $result = $client->getPodTelemetry('pod-123');

    expect($result)->toBeNull();
});

it('returns null when latestTelemetry is not array', function () {
    Http::fake([
        'https://api.runpod.io/graphql' => Http::response([
            'data' => ['pod' => ['latestTelemetry' => 'invalid']],
        ], 200),
    ]);

    $client = new RunPodGraphQLClient('test-key');
    $result = $client->getPodTelemetry('pod-123');

    expect($result)->toBeNull();
});

it('throws when apiKey is empty', function () {
    $client = new RunPodGraphQLClient('');

    $client->getPodTelemetry('pod-123');
})->throws(RunPodApiKeyNotConfiguredException::class);

it('sends podId in variables', function () {
    Http::fake([
        'https://api.runpod.io/graphql' => Http::response(['data' => ['pod' => null]], 200),
    ]);

    $client = new RunPodGraphQLClient('test-key');
    $client->getPodTelemetry('pod-xyz');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $body['variables']['input']['podId'] === 'pod-xyz';
    });
});
