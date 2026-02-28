---
title: Guardrails
navigation:
  priority: 50
  group: API Reference
---

# Guardrails

Limit RunPod API usage to avoid unexpected spend. When a limit is exceeded, `GuardrailsExceededException` is thrown and `GuardrailsTripped` is dispatched.

## Configuration

Configure limits in `config/runpod.php` under `guardrails.limits`:

- `pods.pods_max` – maximum total pods
- `pods.pods_running_max` – maximum running pods
- `serverless.endpoints_max` – maximum serverless endpoints
- `serverless.workers_total_max` – maximum total serverless workers
- `storage.network_volumes_max` – maximum network volumes
- `storage.volume_size_gb_max` – maximum total storage in GB

## Events

Listen for `GuardrailsTripped` to react when a limit is exceeded:

```php
use ChrisThompsonTLDR\LaravelRunPod\Events\GuardrailsTripped;

Event::listen(GuardrailsTripped::class, function (GuardrailsTripped $event) {
    Log::warning('RunPod guardrail tripped', [
        'service'     => $event->service,
        'limit'       => $event->limit,
        'current'     => $event->current,
        'limit_value' => $event->limitValue,
    ]);
});
```
