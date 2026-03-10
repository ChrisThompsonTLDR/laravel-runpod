<?php

use ChrisThompsonTLDR\LaravelRunPod\RunPodClient;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;
use Illuminate\Support\Facades\Http;

uses(TestCase::class);

covers(RunPodClient::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

it('returns pod data from getPod when successful', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-123' => Http::response(['id' => 'pod-123', 'desiredStatus' => 'RUNNING'], 200),
    ]);

    $client = new RunPodClient('test-key');
    $pod = $client->getPod('pod-123');

    expect($pod)->toBe(['id' => 'pod-123', 'desiredStatus' => 'RUNNING']);
});

it('returns null from getPod when request fails', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-123' => Http::response([], 404),
    ]);

    $client = new RunPodClient('test-key');
    $pod = $client->getPod('pod-123');

    expect($pod)->toBeNull();
});

it('returns array from listPods when successful', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response([['id' => 'pod-1']], 200),
    ]);

    $client = new RunPodClient('test-key');
    $pods = $client->listPods();

    expect($pods)->toBe([['id' => 'pod-1']]);
});

it('returns empty array from listPods when request fails', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response([], 500),
    ]);

    $client = new RunPodClient('test-key');
    $pods = $client->listPods();

    expect($pods)->toBe([]);
});

it('returns true from deletePod when status is 204', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-123' => Http::response(null, 204),
    ]);

    $client = new RunPodClient('test-key');
    $result = $client->deletePod('pod-123');

    expect($result)->toBeTrue();
});

it('returns false from deletePod when status is not 204', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-123' => Http::response([], 200),
    ]);

    $client = new RunPodClient('test-key');
    $result = $client->deletePod('pod-123');

    expect($result)->toBeFalse();
});

it('returns pod from createPod when successful', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods' => Http::response(['id' => 'pod-new', 'name' => 'test'], 201),
    ]);

    $client = new RunPodClient('test-key');
    $pod = $client->createPod(['imageName' => 'runpod/pytorch', 'name' => 'test']);

    expect($pod)->toBe(['id' => 'pod-new', 'name' => 'test']);
});

it('returns null from createPod when request fails', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods' => Http::response([], 400),
    ]);

    $client = new RunPodClient('test-key');
    $pod = $client->createPod(['imageName' => 'runpod/pytorch']);

    expect($pod)->toBeNull();
});

it('returns pod from updatePod when successful', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-1*' => Http::response(['id' => 'pod-1', 'name' => 'updated'], 200),
    ]);

    $client = new RunPodClient('test-key');
    $pod = $client->updatePod('pod-1', ['name' => 'updated']);

    expect($pod)->toBe(['id' => 'pod-1', 'name' => 'updated']);
});

it('returns array from startPod when successful', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-1/start' => Http::response(['desiredStatus' => 'RUNNING'], 200),
    ]);

    $client = new RunPodClient('test-key');
    $result = $client->startPod('pod-1');

    expect($result)->toBe(['desiredStatus' => 'RUNNING']);
});

it('returns null from startPod when request fails', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-1/start' => Http::response([], 500),
    ]);

    $client = new RunPodClient('test-key');
    $result = $client->startPod('pod-1');

    expect($result)->toBeNull();
});

it('returns array from stopPod when successful', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-1/stop' => Http::response(['desiredStatus' => 'EXITED'], 200),
    ]);

    $client = new RunPodClient('test-key');
    $result = $client->stopPod('pod-1');

    expect($result)->toBe(['desiredStatus' => 'EXITED']);
});

it('returns array from listEndpoints when successful', function () {
    Http::fake([
        'https://rest.runpod.io/v1/endpoints' => Http::response([['id' => 'ep-1']], 200),
    ]);

    $client = new RunPodClient('test-key');
    $endpoints = $client->listEndpoints();

    expect($endpoints)->toBe([['id' => 'ep-1']]);
});

it('returns empty array from listEndpoints when request fails', function () {
    Http::fake([
        'https://rest.runpod.io/v1/endpoints' => Http::response([], 500),
    ]);

    $client = new RunPodClient('test-key');
    $endpoints = $client->listEndpoints();

    expect($endpoints)->toBe([]);
});

it('returns array from listNetworkVolumes when successful', function () {
    Http::fake([
        'https://rest.runpod.io/v1/networkvolumes' => Http::response([['id' => 'vol-1', 'size' => 50]], 200),
    ]);

    $client = new RunPodClient('test-key');
    $volumes = $client->listNetworkVolumes();

    expect($volumes)->toBe([['id' => 'vol-1', 'size' => 50]]);
});

it('returns empty array from listNetworkVolumes when request fails', function () {
    Http::fake([
        'https://rest.runpod.io/v1/networkvolumes' => Http::response([], 500),
    ]);

    $client = new RunPodClient('test-key');
    $volumes = $client->listNetworkVolumes();

    expect($volumes)->toBe([]);
});

it('returns array from listPods with filters', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response([['id' => 'pod-1']], 200),
    ]);

    $client = new RunPodClient('test-key');
    $pods = $client->listPods(['computeType' => 'CPU']);

    expect($pods)->toBe([['id' => 'pod-1']]);
});

it('returns array from resetPod when successful', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-1/reset' => Http::response(['desiredStatus' => 'RUNNING'], 200),
    ]);

    $client = new RunPodClient('test-key');
    $result = $client->resetPod('pod-1');

    expect($result)->toBe(['desiredStatus' => 'RUNNING']);
});

it('returns array from restartPod when successful', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-1/restart' => Http::response(['desiredStatus' => 'RUNNING'], 200),
    ]);

    $client = new RunPodClient('test-key');
    $result = $client->restartPod('pod-1');

    expect($result)->toBe(['desiredStatus' => 'RUNNING']);
});

it('returns endpoint from getEndpoint when successful', function () {
    Http::fake([
        'https://rest.runpod.io/v1/endpoints/ep-1' => Http::response(['id' => 'ep-1', 'templateId' => 't1'], 200),
    ]);

    $client = new RunPodClient('test-key');
    $endpoint = $client->getEndpoint('ep-1');

    expect($endpoint)->toBe(['id' => 'ep-1', 'templateId' => 't1']);
});

it('returns endpoint from createEndpoint when successful', function () {
    Http::fake([
        'https://rest.runpod.io/v1/endpoints' => Http::response(['id' => 'ep-new'], 201),
    ]);

    $client = new RunPodClient('test-key');
    $endpoint = $client->createEndpoint(['templateId' => 't1', 'name' => 'test']);

    expect($endpoint)->toBe(['id' => 'ep-new']);
});

it('returns true from deleteEndpoint when status is 204', function () {
    Http::fake([
        'https://rest.runpod.io/v1/endpoints/ep-1' => Http::response(null, 204),
    ]);

    $client = new RunPodClient('test-key');
    expect($client->deleteEndpoint('ep-1'))->toBeTrue();
});

it('returns array from listTemplates when successful', function () {
    Http::fake([
        'https://rest.runpod.io/v1/templates' => Http::response([['id' => 't1']], 200),
    ]);

    $client = new RunPodClient('test-key');
    $templates = $client->listTemplates();

    expect($templates)->toBe([['id' => 't1']]);
});

it('returns volume from getNetworkVolume when successful', function () {
    Http::fake([
        'https://rest.runpod.io/v1/networkvolumes/vol-1' => Http::response(['id' => 'vol-1', 'size' => 50], 200),
    ]);

    $client = new RunPodClient('test-key');
    $volume = $client->getNetworkVolume('vol-1');

    expect($volume)->toBe(['id' => 'vol-1', 'size' => 50]);
});

it('returns array from listContainerRegistryAuths when successful', function () {
    Http::fake([
        'https://rest.runpod.io/v1/containerregistryauth' => Http::response([['id' => 'auth-1']], 200),
    ]);

    $client = new RunPodClient('test-key');
    $auths = $client->listContainerRegistryAuths();

    expect($auths)->toBe([['id' => 'auth-1']]);
});

it('returns array from getPodBilling when successful', function () {
    Http::fake([
        'https://rest.runpod.io/v1/billing/pods*' => Http::response([['podId' => 'pod-1']], 200),
    ]);

    $client = new RunPodClient('test-key');
    $billing = $client->getPodBilling();

    expect($billing)->toBe([['podId' => 'pod-1']]);
});

// =============================================================================
// normalizeListResponse (via listPods / listEndpoints)
// =============================================================================

it('normalizes listPods when API returns data wrapper', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response(['data' => [['id' => 'pod-1'], ['id' => 'pod-2']]], 200),
    ]);

    $client = new RunPodClient('test-key');
    $pods = $client->listPods();

    expect($pods)->toBe([['id' => 'pod-1'], ['id' => 'pod-2']]);
});

it('normalizes listNetworkVolumes when API returns data wrapper', function () {
    Http::fake([
        'https://rest.runpod.io/v1/networkvolumes' => Http::response(['data' => [['id' => 'vol-1']]], 200),
    ]);

    $client = new RunPodClient('test-key');
    $volumes = $client->listNetworkVolumes();

    expect($volumes)->toBe([['id' => 'vol-1']]);
});

it('normalizes listTemplates when API returns data wrapper', function () {
    Http::fake([
        'https://rest.runpod.io/v1/templates' => Http::response(['data' => [['id' => 't1', 'name' => 'base']]], 200),
    ]);

    $client = new RunPodClient('test-key');
    $templates = $client->listTemplates();

    expect($templates)->toBe([['id' => 't1', 'name' => 'base']]);
});

// =============================================================================
// getLastError
// =============================================================================

it('returns null from getLastError initially', function () {
    $client = new RunPodClient('test-key');

    expect($client->getLastError())->toBeNull();
});

it('sets lastError when createPod fails', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods' => Http::response('Bad request', 400),
    ]);

    $client = new RunPodClient('test-key');
    $client->createPod(['imageName' => 'img']);

    expect($client->getLastError())->toBe('Bad request');
});

it('clears lastError on successful createPod', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods' => Http::response(['id' => 'pod-1'], 201),
    ]);

    $client = new RunPodClient('test-key');
    $client->createPod(['imageName' => 'img', 'name' => 'test']);

    expect($client->getLastError())->toBeNull();
});

// =============================================================================
// Custom baseUrl
// =============================================================================

it('uses custom baseUrl when provided', function () {
    Http::fake([
        'https://custom.runpod.io/v1/pods*' => Http::response([], 200),
    ]);

    $client = new RunPodClient('test-key', 'https://custom.runpod.io/v1');
    $client->listPods();

    Http::assertSent(fn ($req) => str_starts_with($req->url(), 'https://custom.runpod.io/v1/pods'));
});

// =============================================================================
// runServerlessSync
// =============================================================================

it('returns response from runServerlessSync when successful', function () {
    Http::fake([
        'https://api.runpod.ai/v2/ep-123/runsync' => Http::response(['output' => 'result', 'status' => 'COMPLETED'], 200),
    ]);

    $client = new RunPodClient('test-key');
    $result = $client->runServerlessSync('ep-123', ['prompt' => 'hello']);

    expect($result)->toBe(['output' => 'result', 'status' => 'COMPLETED']);
});

it('returns null from runServerlessSync when request fails', function () {
    Http::fake([
        'https://api.runpod.ai/v2/ep-123/runsync' => Http::response([], 500),
    ]);

    $client = new RunPodClient('test-key');
    $result = $client->runServerlessSync('ep-123', []);

    expect($result)->toBeNull()
        ->and($client->getLastError())->not->toBeNull();
});

it('getTemplateByName returns template when name matches', function () {
    Http::fake([
        'https://rest.runpod.io/v1/templates' => Http::response([['id' => 't1', 'name' => 'my-template']], 200),
    ]);

    $client = new RunPodClient('test-key');

    expect($client->getTemplateByName('my-template'))->toBe(['id' => 't1', 'name' => 'my-template']);
});

it('getTemplateByName returns null when no match', function () {
    Http::fake([
        'https://rest.runpod.io/v1/templates' => Http::response([['id' => 't1', 'name' => 'other']], 200),
    ]);

    $client = new RunPodClient('test-key');

    expect($client->getTemplateByName('nonexistent'))->toBeNull();
});
