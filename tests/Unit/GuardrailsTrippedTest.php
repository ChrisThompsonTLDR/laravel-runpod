<?php

use ChrisThompsonTLDR\LaravelRunPod\Events\GuardrailsTripped;

covers(GuardrailsTripped::class);

it('holds guardrails trip data', function () {
    $event = new GuardrailsTripped('pods', 'pods_max', 5, 5);

    expect($event->service)->toBe('pods')
        ->and($event->limit)->toBe('pods_max')
        ->and($event->current)->toBe(5)
        ->and($event->limitValue)->toBe(5);
});
