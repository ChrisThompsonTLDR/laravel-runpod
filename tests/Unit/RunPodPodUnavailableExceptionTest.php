<?php

use ChrisThompsonTLDR\LaravelRunPod\Exceptions\RunPodPodUnavailableException;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;

uses(TestCase::class);

covers(RunPodPodUnavailableException::class);

it('has expected message when constructed without previous', function () {
    $e = new RunPodPodUnavailableException;

    expect($e->getMessage())->toContain('RunPod pod is unavailable')
        ->and($e->getMessage())->toContain('pod may still be starting')
        ->and($e->getCode())->toBe(0)
        ->and($e->getPrevious())->toBeNull();
});

it('accepts previous throwable', function () {
    $previous = new \RuntimeException('API error');
    $e = new RunPodPodUnavailableException($previous);

    expect($e->getPrevious())->toBe($previous)
        ->and($e->getMessage())->toContain('RunPod pod is unavailable');
});
