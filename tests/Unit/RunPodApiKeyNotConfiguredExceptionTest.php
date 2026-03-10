<?php

use ChrisThompsonTLDR\LaravelRunPod\Exceptions\RunPodApiKeyNotConfiguredException;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;

uses(TestCase::class);

covers(RunPodApiKeyNotConfiguredException::class);

it('message includes RUNPOD_API_KEY config hint', function () {
    $e = new RunPodApiKeyNotConfiguredException;

    expect($e->getMessage())->toContain('RunPod API key not configured')
        ->and($e->getMessage())->toContain('RUNPOD_API_KEY')
        ->and($e->getMessage())->toContain('.env');
});

it('getCode returns zero', function () {
    $e = new RunPodApiKeyNotConfiguredException;

    expect($e->getCode())->toBe(0);
});

it('passes previous exception when provided', function () {
    $previous = new \RuntimeException('inner');
    $e = new RunPodApiKeyNotConfiguredException($previous);

    expect($e->getPrevious())->toBe($previous);
});
