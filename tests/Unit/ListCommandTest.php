<?php

use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(TestCase::class);

covers(\ChrisThompsonTLDR\LaravelRunPod\Console\ListCommand::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

it('warns when no instances configured', function () {
    config(['runpod.instances' => []]);

    $exitCode = Artisan::call('runpod:list');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('No instances configured');
});

it('outputs instance table when instances configured', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response([], 200),
    ]);

    config([
        'runpod.instances' => [
            'example' => [
                'type' => 'pod',
                'image_name' => 'nginx',
                'prune_schedule' => 'everyFiveMinutes',
            ],
        ],
    ]);

    $exitCode = Artisan::call('runpod:list');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('example');
});
