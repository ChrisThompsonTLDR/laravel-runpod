---
title: Installation
navigation:
  priority: 10
  group: Getting Started
---

# Installation

## Composer

```bash
composer require christhompsontldr/laravel-runpod
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=laravel-runpod-config
```

Add the following to `.env`:

```
# RunPod API key (from RunPod Settings > API Keys)
RUNPOD_API_KEY=

# S3-compatible network volume storage
RUNPOD_S3_ACCESS_KEY=
RUNPOD_S3_SECRET_KEY=
RUNPOD_S3_ENDPOINT=https://s3api-eu-ro-1.runpod.io
RUNPOD_S3_REGION=EU-RO-1
RUNPOD_NETWORK_VOLUME_ID=

# Local path to sync files from (default: storage/app/runpod)
RUNPOD_LOAD_PATH=

# S3 prefix that maps to /workspace/data/ on a pod
RUNPOD_REMOTE_PREFIX=data

# Pod inactivity timeout in minutes before auto-prune
RUNPOD_POD_INACTIVITY_MINUTES=2

# Docker image for pod instances
RUNPOD_POD_IMAGE=
```

## Templates

Configure named instances in `config/runpod.php` under `instances`. Each instance can be `type: pod` (persistent, scheduler-based prune) or `type: serverless` (idleTimeout built-in):

```php
'instances' => [
    'pymupdf' => [
        'type' => 'pod',
        'prune_schedule' => 'everyFiveMinutes',
        'pod' => [
            'gpu_count' => 0,
            'name' => env('RUNPOD_POD_NAME', 'eyejay-pymupdf'),
            'ports' => env('RUNPOD_POD_PORTS', '8000/http'),
            'volume_mount_path' => env('RUNPOD_POD_VOLUME_MOUNT', '/workspace'),
            'env' => [
                ['key' => 'PYMUPDF_DATA_DIR', 'value' => '/workspace'],
            ],
        ],
    ],
],
```
