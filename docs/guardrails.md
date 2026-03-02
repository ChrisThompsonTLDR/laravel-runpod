# Guardrails

Guardrails limit RunPod API usage to avoid unexpected spend. When a limit is exceeded, `GuardrailsExceededException` is thrown and the `GuardrailsTripped` event is dispatched.

## Configuration

In `config/runpod.php`:

```php
'guardrails' => [
    'enabled' => true,
    'cache_schedule' => 'everyFifteenMinutes',
    'limits' => [
        'pods' => [
            'pods_max' => 10,           // Total pods
            'pods_running_max' => 5,     // Running pods
        ],
        'serverless' => [
            'endpoints_max' => 5,
            'workers_total_max' => 20,
        ],
        'storage' => [
            'network_volumes_max' => 5,
            'volume_size_gb_max' => 100,
        ],
    ],
],
```

Set a limit to `0` to disable that check.

## When Guardrails Run

- **Before pod creation** — `RunPodPodManager::createPod()` calls `checkBeforeCreatePod()`, which uses fresh API data (not cache) so concurrent workers see real-time pod counts.
- **General check** — `RunPodGuardrails::check()` uses cached usage. The cache is refreshed on `runpod:guardrails` and on the configured `cache_schedule`.

## Listening for Tripped Guardrails

```php
use ChrisThompsonTLDR\LaravelRunPod\Events\GuardrailsTripped;

Event::listen(GuardrailsTripped::class, function (GuardrailsTripped $event) {
    Log::warning('RunPod guardrail tripped', [
        'service' => $event->service,
        'limit' => $event->limit,
        'current' => $event->current,
        'limit_value' => $event->limitValue,
    ]);
});
```

## Exception Types

`GuardrailsExceededException` has static factory methods:

- `GuardrailsExceededException::pods($current, $limit)`
- `GuardrailsExceededException::podsRunning($current, $limit)`
- `GuardrailsExceededException::serverlessEndpoints($current, $limit)`
- `GuardrailsExceededException::serverlessWorkers($current, $limit)`
- `GuardrailsExceededException::storageVolumes($current, $limit)`
- `GuardrailsExceededException::storageSizeGb($current, $limit)`

## Manual Checks

```php
use ChrisThompsonTLDR\LaravelRunPod\RunPodGuardrails;

$guardrails = app(RunPodGuardrails::class);

// Throws if any limit exceeded
$guardrails->check();

// Get cached usage
$usage = $guardrails->getUsage();

// Get fresh usage (no cache)
$usage = $guardrails->getUsageFresh();

// Clear cache
$guardrails->clearCache();
```
