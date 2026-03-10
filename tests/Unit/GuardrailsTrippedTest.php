<?php

use ChrisThompsonTLDR\LaravelRunPod\Events\GuardrailsTripped;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;
use Illuminate\Support\Facades\Event;

uses(TestCase::class);

covers(GuardrailsTripped::class);

// =============================================================================
// Construction and properties
// =============================================================================

it('holds guardrails trip data', function () {
    $event = new GuardrailsTripped('pods', 'pods_max', 5, 5);

    expect($event->service)->toBe('pods')
        ->and($event->limit)->toBe('pods_max')
        ->and($event->current)->toBe(5)
        ->and($event->limitValue)->toBe(5);
});

it('accepts all guardrail service types', function (string $service) {
    $event = new GuardrailsTripped($service, 'limit_key', 1, 2);

    expect($event->service)->toBe($service);
})->with(['pods', 'serverless', 'storage']);

it('accepts all limit key types', function (string $limit) {
    $event = new GuardrailsTripped('pods', $limit, 10, 5);

    expect($event->limit)->toBe($limit);
})->with(['pods_max', 'pods_running_max', 'endpoints_max', 'workers_total_max', 'network_volumes_max', 'volume_size_gb_max']);

// =============================================================================
// Type tests
// =============================================================================

it('service is string', function () {
    $event = new GuardrailsTripped('pods', 'pods_max', 1, 1);

    expect($event->service)->toBeString();
})->group('type');

it('limit is string', function () {
    $event = new GuardrailsTripped('pods', 'pods_max', 1, 1);

    expect($event->limit)->toBeString();
})->group('type');

it('current is int', function () {
    $event = new GuardrailsTripped('pods', 'pods_max', 5, 5);

    expect($event->current)->toBeInt();
})->group('type');

it('limitValue is int', function () {
    $event = new GuardrailsTripped('pods', 'pods_max', 5, 10);

    expect($event->limitValue)->toBeInt();
})->group('type');

// =============================================================================
// Dispatchable
// =============================================================================

it('is dispatchable via static dispatch', function () {
    Event::fake([GuardrailsTripped::class]);

    GuardrailsTripped::dispatch('pods', 'pods_max', 3, 2);

    Event::assertDispatched(GuardrailsTripped::class, function (GuardrailsTripped $e) {
        return $e->service === 'pods'
            && $e->limit === 'pods_max'
            && $e->current === 3
            && $e->limitValue === 2;
    });
});

it('can be dispatched via Event::dispatch', function () {
    Event::fake([GuardrailsTripped::class]);

    $event = new GuardrailsTripped('serverless', 'workers_total_max', 25, 20);
    event($event);

    Event::assertDispatched(GuardrailsTripped::class, function (GuardrailsTripped $e) {
        return $e->service === 'serverless'
            && $e->limit === 'workers_total_max'
            && $e->current === 25
            && $e->limitValue === 20;
    });
});

// =============================================================================
// Listener receives correct payload
// =============================================================================

it('listeners receive event with correct payload', function () {
    $received = null;
    Event::listen(GuardrailsTripped::class, function (GuardrailsTripped $event) use (&$received) {
        $received = [
            'service' => $event->service,
            'limit' => $event->limit,
            'current' => $event->current,
            'limitValue' => $event->limitValue,
        ];
    });

    $event = new GuardrailsTripped('storage', 'volume_size_gb_max', 150, 100);
    event($event);

    expect($received)->toBe([
        'service' => 'storage',
        'limit' => 'volume_size_gb_max',
        'current' => 150,
        'limitValue' => 100,
    ]);
});
