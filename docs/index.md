# Laravel RunPod Documentation

Laravel integration for the [RunPod REST API](https://rest.runpod.io/v1) and RunPod S3-compatible network volume storage. Provides a fluent, Laravel-esque interface for managing pods, serverless endpoints, network volumes, and file storage.

## Features

- **RunPod REST API client** — Full access to pods, serverless endpoints, network volumes, templates, container registry auths, and billing
- **Fluent control plane** — `RunPod::for(Job::class)->instance('example')->start()` for pod lifecycle and file management
- **S3 file management** — Sync files to/from RunPod network volumes via `RunPodFileManager`
- **Named instances** — Multiple pod/serverless configs in `config/runpod.php`
- **Guardrails** — Usage limits (pods, serverless, storage) with `GuardrailsExceededException` and `GuardrailsTripped` event
- **Artisan commands** — Sync, start, list, prune, guardrails, stats, dashboard, flush, inspect
- **Web dashboard** — Livewire v4 SPC at `/runpod/dashboard/{instance?}` (protected by `viewRunpod` gate)
- **Terminal dashboard** — Optional `runpod:dashboard` with Termwind Live
- **RefreshesRunPod trait** — Keeps pods alive during job execution via `last_run_at`
- **Scheduled tasks** — Auto-prune, guardrails cache refresh, stats refresh

## Requirements

- PHP 8.3+
- Laravel 11 or 12
- `league/flysystem-aws-s3-v3` (installed automatically)

## Quick Start

```bash
composer require christhompsontldr/laravel-runpod
php artisan vendor:publish --tag=laravel-runpod-config
```

Add to `.env`:

```
RUNPOD_API_KEY=your_api_key
RUNPOD_S3_ACCESS_KEY=your_s3_access_key
RUNPOD_S3_SECRET_KEY=your_s3_secret_key
RUNPOD_S3_ENDPOINT=https://s3api-us-md-1.runpod.io
RUNPOD_S3_REGION=US-MD-1
RUNPOD_NETWORK_VOLUME_ID=your_volume_id
```

The published config uses `RUNPOD_NETWORK_VOLUME_ID` as the S3 bucket (network volume ID = bucket name).

## Documentation

- [Installation](installation.md)
- [Configuration](configuration.md)
- [Usage](usage.md) — API client, fluent control plane, file management, RefreshesRunPod
- [Artisan Commands](artisan-commands.md)
- [Guardrails](guardrails.md)
- [Dashboards](dashboards.md)
- [Storage](storage.md)
