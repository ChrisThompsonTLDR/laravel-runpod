<?php

use ChrisThompsonTLDR\LaravelRunPod\Console\GuardrailsCommand;
use ChrisThompsonTLDR\LaravelRunPod\RunPodGuardrails;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(TestCase::class);

covers(GuardrailsCommand::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

it('refreshes usage cache when run without options', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response([], 200),
        'https://rest.runpod.io/v1/endpoints*' => Http::response([], 200),
        'https://rest.runpod.io/v1/networkvolumes*' => Http::response([], 200),
    ]);

    $exitCode = Artisan::call('runpod:guardrails');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Guardrails usage cache refreshed');
});

it('clears cache only when --clear option passed', function () {
    $exitCode = Artisan::call('runpod:guardrails', ['--clear' => true]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Guardrails cache cleared');
});

it('returns failure and outputs error when getUsage throws', function () {
    $mockGuardrails = \Mockery::mock(RunPodGuardrails::class);
    $mockGuardrails->shouldReceive('clearCache')->once();
    $mockGuardrails->shouldReceive('getUsage')->once()->andThrow(new \RuntimeException('API unreachable'));

    app()->instance(RunPodGuardrails::class, $mockGuardrails);

    $exitCode = Artisan::call('runpod:guardrails');

    expect($exitCode)->toBe(1);

    $output = Artisan::output();
    expect($output)->toContain('Guardrails check failed');
});
