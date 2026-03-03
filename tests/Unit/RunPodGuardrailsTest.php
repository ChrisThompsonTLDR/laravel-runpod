<?php

use ChrisThompsonTLDR\LaravelRunPod\Events\GuardrailsTripped;
use ChrisThompsonTLDR\LaravelRunPod\Exceptions\GuardrailsExceededException;
use ChrisThompsonTLDR\LaravelRunPod\RunPodGuardrails;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(TestCase::class);

covers(RunPodGuardrails::class);

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();
});

it('does nothing when guardrails disabled', function () {
    config(['runpod.guardrails.enabled' => false]);

    $guardrails = app(RunPodGuardrails::class);
    $guardrails->check();

    expect(true)->toBeTrue();
});

it('passes check when usage under limits', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response([], 200),
        'https://rest.runpod.io/v1/endpoints*' => Http::response([], 200),
        'https://rest.runpod.io/v1/networkvolumes*' => Http::response([], 200),
    ]);

    config([
        'runpod.guardrails.limits' => [
            'pods' => ['pods_max' => 10, 'pods_running_max' => 5],
        ],
    ]);

    $guardrails = app(RunPodGuardrails::class);
    $guardrails->check();

    expect(true)->toBeTrue();
});

it('throws when pods_max exceeded', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response(
            [['id' => '1'], ['id' => '2'], ['id' => '3']],
            200
        ),
        'https://rest.runpod.io/v1/endpoints*' => Http::response([], 200),
        'https://rest.runpod.io/v1/networkvolumes*' => Http::response([], 200),
    ]);

    config([
        'runpod.guardrails.limits' => [
            'pods' => ['pods_max' => 2, 'pods_running_max' => 5],
        ],
    ]);

    $guardrails = app(RunPodGuardrails::class);

    $guardrails->check();
})->throws(GuardrailsExceededException::class);

it('dispatches GuardrailsTripped when limit exceeded', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response(
            [['id' => '1'], ['id' => '2'], ['id' => '3']],
            200
        ),
        'https://rest.runpod.io/v1/endpoints*' => Http::response([], 200),
        'https://rest.runpod.io/v1/networkvolumes*' => Http::response([], 200),
    ]);

    config([
        'runpod.guardrails.limits' => [
            'pods' => ['pods_max' => 2],
        ],
    ]);

    Event::listen(GuardrailsTripped::class, function (GuardrailsTripped $event) {
        expect($event->service)->toBe('pods')
            ->and($event->limit)->toBe('pods_max')
            ->and($event->current)->toBe(3)
            ->and($event->limitValue)->toBe(2);
    });

    $guardrails = app(RunPodGuardrails::class);

    try {
        $guardrails->check();
    } catch (GuardrailsExceededException) {
        // Expected
    }
});

it('getUsage returns aggregated usage from client', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response([
            ['desiredStatus' => 'RUNNING'],
            ['desiredStatus' => 'STOPPED'],
        ], 200),
        'https://rest.runpod.io/v1/endpoints*' => Http::response([
            ['workersMax' => 5],
            ['workersMax' => 3],
        ], 200),
        'https://rest.runpod.io/v1/networkvolumes*' => Http::response([
            ['size' => 20],
            ['volumeInGb' => 30],
        ], 200),
    ]);

    $guardrails = app(RunPodGuardrails::class);
    $usage = $guardrails->getUsage();

    expect($usage['pods_count'])->toBe(2)
        ->and($usage['pods_running_count'])->toBe(1)
        ->and($usage['endpoints_count'])->toBe(2)
        ->and($usage['workers_total'])->toBe(8)
        ->and($usage['network_volumes_count'])->toBe(2)
        ->and($usage['storage_total_gb'])->toBe(50.0);
});

it('clearCache forgets cache key', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response([], 200),
        'https://rest.runpod.io/v1/endpoints*' => Http::response([], 200),
        'https://rest.runpod.io/v1/networkvolumes*' => Http::response([], 200),
    ]);

    $guardrails = app(RunPodGuardrails::class);
    $guardrails->getUsage();
    $guardrails->clearCache();

    $guardrails->getUsage();
    Http::assertSentCount(6); // 2 calls × 3 endpoints each
});

it('getCacheTtlSeconds maps schedule to seconds', function () {
    $guardrails = app(RunPodGuardrails::class);
    $method = (new \ReflectionClass(RunPodGuardrails::class))->getMethod('getCacheTtlSeconds');
    $method->setAccessible(true);

    config(['runpod.guardrails.cache_schedule' => 'everyFiveMinutes']);
    expect($method->invoke($guardrails))->toBe(300);

    config(['runpod.guardrails.cache_schedule' => 'hourly']);
    expect($method->invoke($guardrails))->toBe(3600);

    config(['runpod.guardrails.cache_schedule' => 'unknown']);
    expect($method->invoke($guardrails))->toBe(900);
});

it('throws when pods_running_max exceeded', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response(
            [
                ['id' => '1', 'desiredStatus' => 'RUNNING'],
                ['id' => '2', 'desiredStatus' => 'RUNNING'],
                ['id' => '3', 'desiredStatus' => 'RUNNING'],
            ],
            200
        ),
        'https://rest.runpod.io/v1/endpoints*' => Http::response([], 200),
        'https://rest.runpod.io/v1/networkvolumes*' => Http::response([], 200),
    ]);

    config([
        'runpod.guardrails.limits' => [
            'pods' => ['pods_max' => 10, 'pods_running_max' => 2],
        ],
    ]);

    $guardrails = app(RunPodGuardrails::class);

    $guardrails->check();
})->throws(GuardrailsExceededException::class);

it('throws when serverless endpoints_max exceeded', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response([], 200),
        'https://rest.runpod.io/v1/endpoints*' => Http::response(
            [['id' => 'ep1'], ['id' => 'ep2'], ['id' => 'ep3']],
            200
        ),
        'https://rest.runpod.io/v1/networkvolumes*' => Http::response([], 200),
    ]);

    config([
        'runpod.guardrails.limits' => [
            'serverless' => ['endpoints_max' => 2],
        ],
    ]);

    $guardrails = app(RunPodGuardrails::class);

    $guardrails->check();
})->throws(GuardrailsExceededException::class);

it('throws when serverless workers_total_max exceeded', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response([], 200),
        'https://rest.runpod.io/v1/endpoints*' => Http::response(
            [['workersMax' => 10], ['workersMax' => 5]],
            200
        ),
        'https://rest.runpod.io/v1/networkvolumes*' => Http::response([], 200),
    ]);

    config([
        'runpod.guardrails.limits' => [
            'serverless' => ['workers_total_max' => 10],
        ],
    ]);

    $guardrails = app(RunPodGuardrails::class);

    $guardrails->check();
})->throws(GuardrailsExceededException::class);

it('throws when storage network_volumes_max exceeded', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response([], 200),
        'https://rest.runpod.io/v1/endpoints*' => Http::response([], 200),
        'https://rest.runpod.io/v1/networkvolumes*' => Http::response(
            [['id' => 'v1'], ['id' => 'v2'], ['id' => 'v3']],
            200
        ),
    ]);

    config([
        'runpod.guardrails.limits' => [
            'storage' => ['network_volumes_max' => 2],
        ],
    ]);

    $guardrails = app(RunPodGuardrails::class);

    $guardrails->check();
})->throws(GuardrailsExceededException::class);

it('throws when storage volume_size_gb_max exceeded', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response([], 200),
        'https://rest.runpod.io/v1/endpoints*' => Http::response([], 200),
        'https://rest.runpod.io/v1/networkvolumes*' => Http::response(
            [['size' => 50], ['size' => 60]],
            200
        ),
    ]);

    config([
        'runpod.guardrails.limits' => [
            'storage' => ['volume_size_gb_max' => 100],
        ],
    ]);

    $guardrails = app(RunPodGuardrails::class);

    $guardrails->check();
})->throws(GuardrailsExceededException::class);

it('checkBeforeCreatePod throws when pods_max exceeded', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response(
            [['id' => '1'], ['id' => '2'], ['id' => '3']],
            200
        ),
        'https://rest.runpod.io/v1/endpoints*' => Http::response([], 200),
        'https://rest.runpod.io/v1/networkvolumes*' => Http::response([], 200),
    ]);

    config([
        'runpod.guardrails.limits' => [
            'pods' => ['pods_max' => 2],
        ],
    ]);

    $guardrails = app(RunPodGuardrails::class);

    $guardrails->checkBeforeCreatePod();
})->throws(GuardrailsExceededException::class);

it('checkBeforeCreatePod throws when pods_running_max exceeded', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response(
            [
                ['id' => '1', 'desiredStatus' => 'RUNNING'],
                ['id' => '2', 'desiredStatus' => 'RUNNING'],
            ],
            200
        ),
        'https://rest.runpod.io/v1/endpoints*' => Http::response([], 200),
        'https://rest.runpod.io/v1/networkvolumes*' => Http::response([], 200),
    ]);

    config([
        'runpod.guardrails.limits' => [
            'pods' => ['pods_running_max' => 1],
        ],
    ]);

    $guardrails = app(RunPodGuardrails::class);

    $guardrails->checkBeforeCreatePod();
})->throws(GuardrailsExceededException::class);

it('check passes when pod limits empty', function () {
    Http::fake([
        'https://rest.runpod.io/v1/pods*' => Http::response([['id' => '1']], 200),
        'https://rest.runpod.io/v1/endpoints*' => Http::response([], 200),
        'https://rest.runpod.io/v1/networkvolumes*' => Http::response([], 200),
    ]);

    config(['runpod.guardrails.limits' => ['pods' => []]]);

    $guardrails = app(RunPodGuardrails::class);
    $guardrails->check();

    expect(true)->toBeTrue();
});
