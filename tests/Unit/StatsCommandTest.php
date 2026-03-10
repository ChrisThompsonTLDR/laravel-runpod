<?php

use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

uses(TestCase::class);

covers(\ChrisThompsonTLDR\LaravelRunPod\Console\StatsCommand::class);

it('returns failure when instance unknown', function () {
    config(['runpod.instances' => ['example' => []]]);

    $exitCode = Artisan::call('runpod:stats', ['instance' => 'unknown']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Unknown instance');
});

it('outputs no pod instances when only serverless configured', function () {
    config([
        'runpod.instances' => [
            'serverless' => ['type' => 'serverless'],
        ],
    ]);

    $exitCode = Artisan::call('runpod:stats');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('No pod instances');
});
