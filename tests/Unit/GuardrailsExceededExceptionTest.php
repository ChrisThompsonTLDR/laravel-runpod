<?php

use ChrisThompsonTLDR\LaravelRunPod\Exceptions\GuardrailsExceededException;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;

uses(TestCase::class);

covers(GuardrailsExceededException::class);

it('constructs with service limit and usage', function () {
    $e = new GuardrailsExceededException('pods', 'pods_max', 5, 3);

    expect($e->getMessage())->toContain('RunPod API guardrail exceeded')
        ->and($e->getMessage())->toContain('pods')
        ->and($e->getMessage())->toContain('pods_max')
        ->and($e->getMessage())->toContain('5')
        ->and($e->getMessage())->toContain('3');
});

it('pods factory creates correct exception', function () {
    $e = GuardrailsExceededException::pods(4, 3);

    expect($e->getMessage())->toContain('pods_max');
});

it('podsRunning factory creates correct exception', function () {
    $e = GuardrailsExceededException::podsRunning(2, 1);

    expect($e->getMessage())->toContain('pods_running_max');
});

it('serverlessEndpoints factory creates correct exception', function () {
    $e = GuardrailsExceededException::serverlessEndpoints(5, 3);

    expect($e->getMessage())->toContain('endpoints_max');
});

it('serverlessWorkers factory creates correct exception', function () {
    $e = GuardrailsExceededException::serverlessWorkers(10, 5);

    expect($e->getMessage())->toContain('workers_total_max');
});

it('storageVolumes factory creates correct exception', function () {
    $e = GuardrailsExceededException::storageVolumes(3, 2);

    expect($e->getMessage())->toContain('network_volumes_max');
});

it('storageSizeGb factory creates correct exception', function () {
    $e = GuardrailsExceededException::storageSizeGb(200, 100);

    expect($e->getMessage())->toContain('volume_size_gb_max');
});

it('apiRequestsPerMinute factory creates correct exception', function () {
    $e = GuardrailsExceededException::apiRequestsPerMinute(100, 60);

    expect($e->getMessage())->toContain('requests_per_minute');
});
