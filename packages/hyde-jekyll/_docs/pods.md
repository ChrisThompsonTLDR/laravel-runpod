---
title: Pods
navigation:
  priority: 20
  group: API Reference
---

# Pods

Persistent GPU instances with full lifecycle management.

## RunPod API Client

Inject or resolve `RunPodClient` to call the full RunPod REST API directly:

```php
use ChrisThompsonTLDR\LaravelRunPod\RunPodClient;

$client = app(RunPodClient::class);

// List and get
$pods = $client->listPods();
$pod  = $client->getPod($podId);

// Create
$pod = $client->createPod([
    'imageName' => 'runpod/base:0.4.0',
    'name' => 'my-pod',
    // ... more options
]);

// Lifecycle
$client->startPod($podId);
$client->stopPod($podId);
$client->restartPod($podId);
$client->resetPod($podId);
$client->updatePod($podId, ['name' => 'new-name']);
$client->deletePod($podId);
```

## Fluent Control Plane

Use the `RunPod` class for a higher-level workflow:

```php
use ChrisThompsonTLDR\LaravelRunPod\RunPod;

$runPod = app(RunPod::class)->for(PymupdfJob::class);

// File operations via the configured S3 disk
$runPod->disk('runpod')->ensure($filename);

// Start a named pod instance (configured in config/runpod.php)
$pod = $runPod->instance('pymupdf')->start();
$url = $pod['url'];
```

## Named Instances

Configure named pod instances in `config/runpod.php` under `instances`. Each can specify `type: pod` with `prune_schedule` for automatic cleanup of inactive pods.
