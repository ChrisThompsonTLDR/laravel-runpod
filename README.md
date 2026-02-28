# Laravel RunPod

Laravel integration for RunPod S3-compatible storage and [SoipoServices/runpod](https://github.com/SoipoServices/runpod). Configures a RunPod network volume as an S3 disk and provides fluent file management with proactive sync from local load path.

## Installation

```bash
composer require chris/laravel-runpod
```

## Configuration

Publish the config:

```bash
php artisan vendor:publish --tag=laravel-runpod-config
```

Add to `.env`:

```
RUNPOD_S3_ACCESS_KEY=       # From RunPod Settings → S3 API Keys
RUNPOD_S3_SECRET_KEY=       # rps_xxx from S3 API Keys
RUNPOD_S3_ENDPOINT=https://s3api-eu-ro-1.runpod.io
RUNPOD_S3_REGION=EU-RO-1
RUNPOD_NETWORK_VOLUME_ID=   # Your network volume ID
RUNPOD_LOAD_PATH=           # Local path to sync from (default: storage/app/insurance-journals)
RUNPOD_REMOTE_PREFIX=data   # S3 prefix (maps to /workspace/data/ on pod)
```

## Usage

### Fluent file management

```php
use Illuminate\Support\Facades\Storage;

// Ensure a file is on RunPod (syncs if missing)
Storage::runpod()->ensure('document.pdf');

// Sync a specific file from load path
Storage::runpod()->syncFrom('path/to/file.pdf');

// Put content directly
Storage::runpod()->put('data/file.pdf', $contents);

// Check existence
Storage::runpod()->exists('data/file.pdf');
```

### Artisan command

Sync entire load path to RunPod:

```bash
php artisan runpod:sync
```

Sync a specific file or directory:

```bash
php artisan runpod:sync --path=document.pdf
php artisan runpod:sync --path=subdir/
```

### Scheduled sync

In `routes/console.php`:

```php
Schedule::command('runpod:sync')->everyFiveMinutes();
```

## Storage cost

RunPod network volume: **$0.07/GB/mo** (first 1TB). For 36 GB: **$2.52/month**.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- RunPod network volume in an [S3-compatible datacenter](https://docs.runpod.io/storage/s3-api#datacenter-availability)
