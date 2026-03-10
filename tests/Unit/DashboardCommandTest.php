<?php

use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

uses(TestCase::class);

covers(\ChrisThompsonTLDR\LaravelRunPod\Console\DashboardCommand::class);

it('returns failure when Termwind Live not installed', function () {
    $exitCode = Artisan::call('runpod:dashboard');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Termwind Live');
});
