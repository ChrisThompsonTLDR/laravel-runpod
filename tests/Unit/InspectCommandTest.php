<?php

use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

uses(TestCase::class);

covers(\ChrisThompsonTLDR\LaravelRunPod\Console\InspectCommand::class);

it('returns failure when instance unknown', function () {
    config(['runpod.instances' => []]);

    $exitCode = Artisan::call('runpod:inspect', ['instance' => 'unknown']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Unknown instance');
});
