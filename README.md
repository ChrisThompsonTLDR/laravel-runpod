# Laravel RunPod

Laravel integration for the [RunPod REST API](https://rest.runpod.io/v1) and RunPod S3-compatible network volume storage. Provides a fluent, Laravel-esque interface for managing pods, serverless endpoints, network volumes, templates, container registry auths, billing, and file storage.

## Requirements

- PHP 8.2+
- Laravel 11 or 12

## Installation

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

## Usage

### RunPod API Client

Inject or resolve `RunPodClient` to call the full RunPod REST API directly:

```php
use ChrisThompsonTLDR\LaravelRunPod\RunPodClient;

$client = app(RunPodClient::class);

// Pods
$pods    = $client->listPods();
$pod     = $client->getPod($podId);
$pod     = $client->createPod(['imageName' => 'runpod/base:0.4.0', 'name' => 'my-pod', ...]);
$client->startPod($podId);
$client->stopPod($podId);
$client->restartPod($podId);
$client->resetPod($podId);
$client->updatePod($podId, ['name' => 'new-name']);
$client->deletePod($podId);

// Serverless endpoints
$endpoints = $client->listEndpoints();
$endpoint  = $client->getEndpoint($endpointId);
$endpoint  = $client->createEndpoint(['templateId' => '...', 'name' => 'my-endpoint', ...]);
$client->updateEndpoint($endpointId, ['workersMax' => 5]);
$client->deleteEndpoint($endpointId);

// Network volumes
$volumes = $client->listNetworkVolumes();
$volume  = $client->getNetworkVolume($volumeId);
$volume  = $client->createNetworkVolume(['dataCenterId' => 'EU-RO-1', 'name' => 'my-vol', 'size' => 20]);
$client->updateNetworkVolume($volumeId, ['name' => 'new-name']);
$client->deleteNetworkVolume($volumeId);

// Templates
$templates = $client->listTemplates();
$template  = $client->getTemplate($templateId);
$template  = $client->createTemplate(['imageName' => 'runpod/base:0.4.0', 'name' => 'my-template']);
$client->updateTemplate($templateId, ['name' => 'new-name']);
$client->deleteTemplate($templateId);

// Container registry auths
$auths = $client->listContainerRegistryAuths();
$auth  = $client->getContainerRegistryAuth($authId);
$auth  = $client->createContainerRegistryAuth(['name' => 'my-reg', 'username' => 'user', 'password' => 'pass']);
$client->deleteContainerRegistryAuth($authId);

// Billing
$podBilling     = $client->getPodBilling(['startDate' => '2024-01-01']);
$epBilling      = $client->getEndpointBilling();
$volumeBilling  = $client->getNetworkVolumeBilling();
```

### Fluent control plane

Use the `RunPod` class (or its facade) for a higher-level, Laravel-esque workflow that combines pod lifecycle management and file storage:

```php
use ChrisThompsonTLDR\LaravelRunPod\RunPod;

$runPod = app(RunPod::class)->for(PymupdfJob::class);

// File operations via the configured S3 disk
$runPod->disk('runpod')->ensure($filename);

// Start a named pod instance (configured in config/runpod.php)
$pod = $runPod->instance('pymupdf')->start();
$url = $pod['url'];
```

Or via the facade (add alias in `config/app.php`):

```php
// 'RunPod' => ChrisThompsonTLDR\LaravelRunPod\Facades\RunPod::class

RunPod::for(self::class)->disk('runpod')->ensure($filename);
$pod = RunPod::instance('pymupdf')->start();
```

### Fluent file management

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

### Artisan commands

Sync files from the local load path to RunPod storage:

```bash
php artisan runpod:sync
php artisan runpod:sync --path=document.pdf
php artisan runpod:sync --path=subdir/
```

List configured instances:

```bash
php artisan runpod:list
```

Ensure a RunPod instance is running (create and wait if needed):

```bash
php artisan runpod:start pymupdf
```

Prune inactive pods:

```bash
php artisan runpod:prune
php artisan runpod:prune pymupdf
```

Refresh or clear the guardrails usage cache:

```bash
php artisan runpod:guardrails
php artisan runpod:guardrails --clear
```

### Scheduled sync

In `routes/console.php`:

```php
Schedule::command('runpod:sync')->everyFiveMinutes();
```

### RefreshesRunPod trait

Automatically refresh `last_run_at` after pod work completes to keep the pod alive until the prune timer fires:

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
        $pod = RunPod::instance('pymupdf')->start();

        $this->withRunPodRefresh(function () use ($pod) {
            $response = Http::post($pod['url'].'/extract', [...]);
            // process response
        }); // for() fires automatically in finally
    }
}
```

### Named instances (multiple pods/serverless)

Configure named pod or serverless instances in `config/runpod.php` under `instances`. Each instance has its own state file, prune schedule, and can override `image_name`, `network_volume_id`, etc. Use `php artisan runpod:list` to see configured instances.

```php
'instances' => [
    'pymupdf' => [
        'type' => 'pod',
        'prune_schedule' => 'everyFiveMinutes',
        'pod' => [
            'image_name' => env('RUNPOD_PYMUPDF_IMAGE', env('RUNPOD_POD_IMAGE')),
            'gpu_count' => 0,
            'name' => env('RUNPOD_PYMUPDF_POD_NAME', 'eyejay-pymupdf'),
            'ports' => env('RUNPOD_POD_PORTS', '8000/http'),
            'volume_mount_path' => env('RUNPOD_POD_VOLUME_MOUNT', '/workspace'),
            'env' => [
                ['key' => 'PYMUPDF_DATA_DIR', 'value' => '/workspace'],
            ],
        ],
    ],
    'docling' => [
        'type' => 'pod',
        'prune_schedule' => 'everyFiveMinutes',
        'pod' => [
            'image_name' => env('RUNPOD_DOCLING_IMAGE'),
            'gpu_count' => 1,
            'name' => env('RUNPOD_DOCLING_POD_NAME', 'eyejay-docling'),
            // ... other overrides
        ],
    ],
],
```

- **Start:** `php artisan runpod:start pymupdf` or `runpod:start docling`
- **Prune:** `php artisan runpod:prune pymupdf` (or omit for default)

### Guardrails

Limit RunPod API usage to avoid unexpected spend. When a limit is exceeded, `GuardrailsExceededException` is thrown and `GuardrailsTripped` is dispatched:

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

Configure limits in `config/runpod.php` under `guardrails.limits`:

- `pods.pods_max` - maximum total pods
- `pods.pods_running_max` - maximum running pods
- `serverless.endpoints_max` - maximum serverless endpoints
- `serverless.workers_total_max` - maximum total serverless workers
- `storage.network_volumes_max` - maximum network volumes
- `storage.volume_size_gb_max` - maximum total storage in GB

## Storage cost

RunPod network volume pricing starts at $0.07/GB/month. See [RunPod pricing](https://www.runpod.io/pricing) for current rates.
