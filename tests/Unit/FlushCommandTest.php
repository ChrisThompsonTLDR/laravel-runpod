<?php

use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(TestCase::class);

covers(\ChrisThompsonTLDR\LaravelRunPod\Console\FlushCommand::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

it('deletes pods and endpoints when run with force', function () {
    config(['queue.connections.redis' => null]);

    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response([['id' => 'pod-1', 'name' => 'p1', 'desiredStatus' => 'STOPPED']], 200),
        'https://rest.runpod.io/v1/endpoints*' => Http::response([['id' => 'ep-1', 'name' => 'e1']], 200),
    ]);
    Http::fake([
        'https://rest.runpod.io/v1/pods/pod-1' => Http::response(null, 204),
        'https://rest.runpod.io/v1/endpoints/ep-1' => Http::response(null, 204),
    ]);

    $exitCode = Artisan::call('runpod:flush', ['--force' => true]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Flush complete');
});
