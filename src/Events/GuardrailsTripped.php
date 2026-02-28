<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GuardrailsTripped
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $service,
        public string $limit,
        public int $current,
        public int $limitValue
    ) {}
}
