---
title: Serverless
navigation:
  priority: 30
  group: API Reference
---

# Serverless

Endpoints with auto-scaling workers and built-in idle timeout.

## RunPod API Client

```php
use ChrisThompsonTLDR\LaravelRunPod\RunPodClient;

$client = app(RunPodClient::class);

// List and get
$endpoints = $client->listEndpoints();
$endpoint  = $client->getEndpoint($endpointId);

// Create
$endpoint = $client->createEndpoint([
    'templateId' => '...',
    'name' => 'my-endpoint',
    // ... more options
]);

// Update and delete
$client->updateEndpoint($endpointId, ['workersMax' => 5]);
$client->deleteEndpoint($endpointId);
```

## Named Instances

Configure serverless instances in `config/runpod.php` under `instances` with `type: serverless`. Serverless uses the built-in `idleTimeout` for cost-effective on-demand workloads—no separate prune schedule needed.

## Guardrails

Limit serverless usage with `guardrails.limits.serverless`:

- `endpoints_max` – maximum serverless endpoints
- `workers_total_max` – maximum total serverless workers
