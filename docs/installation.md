# Installation

## Requirements

- PHP 8.3+
- Laravel 11 or 12
- `league/flysystem-aws-s3-v3` (pulled in automatically)

## Install the Package

```bash
composer require christhompsontldr/laravel-runpod
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=laravel-runpod-config
```

This creates `config/runpod.php`.

## Environment Variables

Add the following to `.env`:

```
# RunPod API key (from RunPod Settings > API Keys)
RUNPOD_API_KEY=

# S3-compatible network volume storage
# Create an S3 API key in RunPod Settings > S3 API Keys
RUNPOD_S3_ACCESS_KEY=
RUNPOD_S3_SECRET_KEY=
RUNPOD_S3_ENDPOINT=https://s3api-us-md-1.runpod.io
RUNPOD_S3_REGION=US-MD-1

# Network volume ID (also used as S3 bucket name)
RUNPOD_NETWORK_VOLUME_ID=

# Per-instance overrides (e.g. RUNPOD_PYMUPDF_NETWORK_VOLUME_ID, RUNPOD_PYMUPDF_IMAGE)
# See config/runpod.php instances[].pod for image_name, network_volume_id, etc.

# Local path to sync files from (default: storage/app/runpod)
RUNPOD_LOAD_PATH=

# S3 prefix that maps to /workspace/data/ on a pod (default: data)
RUNPOD_REMOTE_PREFIX=data

# Pod inactivity timeout in minutes before auto-prune (default: 2)
RUNPOD_POD_INACTIVITY_MINUTES=2

# Docker image (optional; config has per-instance defaults)
RUNPOD_POD_IMAGE=
```

### S3 Configuration

RunPod network volumes are accessed via an S3-compatible API. The **network volume ID** serves as the **S3 bucket name**. The published config uses `RUNPOD_NETWORK_VOLUME_ID` for the bucket.

The `runpod` disk is only registered when `key`, `secret`, and `bucket` are all set. See [RunPod S3 API docs](https://docs.runpod.io/storage/s3-api) for endpoint URLs per datacenter.

## Optional: Facade Alias

Add to `config/app.php` aliases:

```php
'RunPod' => ChrisThompsonTLDR\LaravelRunPod\Facades\RunPod::class,
```

## Optional: Web Dashboard Views

To customize the Livewire dashboard:

```bash
php artisan vendor:publish --tag=laravel-runpod-dashboard
```

## Optional: Terminal Dashboard

For the live-updating terminal dashboard:

```bash
composer require nunomaduro/termwind xico2k/termwind-plugin-live
```
