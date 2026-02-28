# Laravel RunPod

Laravel integration for RunPod S3-compatible storage and [SoipoServices/runpod](https://github.com/SoipoServices/runpod). Configures a RunPod network volume as an S3 disk, provides fluent file management with proactive sync, and manages GPU/CPU pods for compute workloads (e.g. PyMuPDF).

## Installation

```bash
composer require christhompsontldr/laravel-runpod
```

## Configuration

Publish the config:

```bash
php artisan vendor:publish --tag=laravel-runpod-config
```

Add to `.env`:

```
# S3 storage (network volume)
RUNPOD_S3_ACCESS_KEY=       # From RunPod Settings → S3 API Keys
RUNPOD_S3_SECRET_KEY=       # rps_xxx from S3 API Keys
RUNPOD_S3_ENDPOINT=https://s3api-eu-ro-1.runpod.io
RUNPOD_S3_REGION=EU-RO-1
RUNPOD_NETWORK_VOLUME_ID=   # Your network volume ID
RUNPOD_LOAD_PATH=           # Local path to sync from (default: storage/app/runpod)
RUNPOD_REMOTE_PREFIX=data   # S3 prefix (maps to /workspace/data/ on pod)

# Pod management (for PyMuPDF etc.)
RUNPOD_API_KEY=             # From RunPod Settings → API Keys
RUNPOD_POD_IMAGE=           # Docker image (e.g. your-org/eyejay-pymupdf:latest)
RUNPOD_POD_INACTIVITY_MINUTES=2
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

### Unified RunPod control plane

Fluent, Laravel-esque API for pods and storage:

```php
use ChrisThompsonTLDR\LaravelRunPod\RunPod;

// Refresh "nickname" (cache key for last_run_at / prune tracking)
$runPod = app(RunPod::class)->refresh(PymupdfJob::class);

// Disk operations (Laravel filesystem methods)
$runPod->disk('runpod')->ensure($filename);

// Start pod instance (configured in config/runpod.php instances)
$pod = $runPod->instance('pymupdf')->start();
$url = $pod['url'];

// Or use the facade (add alias in config/app.php):
// 'RunPod' => ChrisThompsonTLDR\LaravelRunPod\Facades\RunPod::class
RunPod::refresh(self::class)->disk('runpod')->ensure($filename);
$pod = RunPod::instance('pymupdf')->start();
```

Use the `RefreshesRunPod` trait to automatically refresh `last_run_at` after pod work:

```php
use ChrisThompsonTLDR\LaravelRunPod\Concerns\RefreshesRunPod;

class PymupdfJob implements ShouldQueue
{
    use RefreshesRunPod;

    protected function runPodInstance(): string
    {
        return 'pymupdf';
    }

    protected function handleRunPod(): void
    {
        // ... start pod, get $pod ...
        $this->withRunPodRefresh(function () use ($pod) {
            $response = Http::post($pod['url'].'/extract', [...]);
            // ... process response ...
        });  // refresh fires automatically in finally
    }
}
```

**Instances** are configured in `config/runpod.php` under `instances`. Each can be `type: pod` (persistent, scheduler-based prune) or `type: serverless` (idleTimeout built-in). Pod instances get per-instance prune schedules.

```bash
php artisan runpod:prune           # Prune default instance
php artisan runpod:prune pymupdf   # Prune specific instance
```

### Guardrails

Limit RunPod API usage (pods, serverless, storage). When exceeded, `GuardrailsExceededException` is thrown and `GuardrailsTripped` event is dispatched. Usage is cached (default 15 min).

```php
use ChrisThompsonTLDR\LaravelRunPod\Events\GuardrailsTripped;
use Illuminate\Support\Facades\Event;

// Listen for tripped guardrails
Event::listen(GuardrailsTripped::class, function (GuardrailsTripped $event) {
    Log::warning('RunPod guardrail tripped', [
        'service' => $event->service,
        'limit' => $event->limit,
        'current' => $event->current,
        'limit_value' => $event->limitValue,
    ]);
});
```

Config (`config/runpod.php` → `guardrails`):

- `enabled` – Enable/disable guardrails
- `cache_schedule` – How often to refresh usage cache (e.g. `everyFifteenMinutes`)
- `limits.pods` – `pods_max`, `pods_running_max`
- `limits.serverless` – `endpoints_max`, `workers_total_max`
- `limits.storage` – `network_volumes_max`, `volume_size_gb_max`

```bash
php artisan runpod:guardrails      # Refresh usage cache
php artisan runpod:guardrails --clear  # Clear cache
```

## Storage cost

RunPod network volume: **$0.07/GB/mo** (first 1TB). For 36 GB: **$2.52/month**.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- RunPod network volume in an [S3-compatible datacenter](https://docs.runpod.io/storage/s3-api#datacenter-availability)
