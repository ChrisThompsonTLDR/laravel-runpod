---
title: Storage
navigation:
  priority: 40
  group: API Reference
---

# Storage

S3-compatible network volumes with fluent file management.

## RunPod API Client

```php
use ChrisThompsonTLDR\LaravelRunPod\RunPodClient;

$client = app(RunPodClient::class);

// Network volumes
$volumes = $client->listNetworkVolumes();
$volume  = $client->getNetworkVolume($volumeId);
$volume  = $client->createNetworkVolume([
    'dataCenterId' => 'EU-RO-1',
    'name' => 'my-vol',
    'size' => 20
]);
$client->updateNetworkVolume($volumeId, ['name' => 'new-name']);
$client->deleteNetworkVolume($volumeId);
```

## Fluent File Management

Use the `runpod` disk via `Storage` facade:

```php
use Illuminate\Support\Facades\Storage;

// Ensure a file exists on RunPod (syncs from load path if missing)
Storage::runpod()->ensure('document.pdf');

// Sync a specific file from load path
Storage::runpod()->syncFrom('path/to/file.pdf');

// Put content directly
Storage::runpod()->put('data/file.pdf', $contents);

// Check existence
Storage::runpod()->exists('data/file.pdf');
```

## Storage Cost

RunPod network volume pricing starts at $0.07/GB/month. See [RunPod pricing](https://www.runpod.io/pricing) for current rates.
