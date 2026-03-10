<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a guardrail limit is exceeded.
 *
 * Contains no models and is not broadcast, so only Dispatchable is needed.
 */
class GuardrailsTripped
{
    use Dispatchable;

    public function __construct(
        public string $service,
        public string $limit,
        public int $current,
        public int $limitValue
    ) {}
}
