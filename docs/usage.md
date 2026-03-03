# Usage

## RunPod API Client

Inject or resolve `RunPodClient` to call the RunPod REST API directly:

```php
use ChrisThompsonTLDR\LaravelRunPod\RunPodClient;

$client = app(RunPodClient::class);

// Pods
$pods = $client->listPods();
$pod = $client->getPod($podId);
$pod = $client->createPod(['imageName' => 'runpod/base:0.4.0', 'name' => 'my-pod', ...]);
$client->startPod($podId);
$client->stopPod($podId);
$client->restartPod($podId);
$client->resetPod($podId);
$client->updatePod($podId, ['name' => 'new-name']);
$client->deletePod($podId);

// Serverless endpoints
$endpoints = $client->listEndpoints();
$endpoint = $client->getEndpoint($endpointId);
$endpoint = $client->createEndpoint([...]);
$client->updateEndpoint($endpointId, ['workersMax' => 5]);
$client->deleteEndpoint($endpointId);

// Network volumes
$volumes = $client->listNetworkVolumes();
$volume = $client->getNetworkVolume($volumeId);
$volume = $client->createNetworkVolume([...]);
$client->updateNetworkVolume($volumeId, ['name' => 'new-name']);
$client->deleteNetworkVolume($volumeId);

// Templates, container registry auths, billing
$templates = $client->listTemplates();
$auths = $client->listContainerRegistryAuths();
$podBilling = $client->getPodBilling(['startDate' => '2024-01-01']);
```

## Fluent Control Plane

Use the `RunPod` class (or facade) for a higher-level workflow combining pod lifecycle and file storage:

```php
use ChrisThompsonTLDR\LaravelRunPod\RunPod;

$runPod = app(RunPod::class)->for(PymupdfJob::class);

// File operations via the configured S3 disk
$runPod->disk('runpod')->ensure($filename);

// Start a named pod instance
$pod = $runPod->instance('pymupdf')->start();
$url = $pod['url'];
```

With the facade:

```php
RunPod::for(self::class)->disk('runpod')->ensure($filename);
$pod = RunPod::instance('pymupdf')->start();
```

### Methods

| Method | Description |
|--------|-------------|
| `for(string $nickname)` | Set nickname for `last_run_at` tracking and prune scheduling |
| `instance(string $name)` | Select named instance from config |
| `disk(?string $disk)` | Get `RunPodFileManager` for file ops (default: `runpod`) |
| `start()` | Ensure pod is running; create and wait if needed |
| `pod()` | Get full pod details from API (no `last_run_at` update) |
| `url()` | Get pod URL after `start()` |

## File Management

Use `RunPod::disk()` or `RunPod::for(...)->disk()` to get a `RunPodFileManager`:

```php
$fm = RunPod::disk('runpod');

// Ensure file exists on RunPod (syncs from load path if missing)
$fm->ensure('document.pdf');

// Sync a specific file from load path
$fm->syncFrom('/full/path/to/file.pdf');

// Sync entire load path
$fm->syncAll();

// Put content directly
$fm->put('data/file.pdf', $contents);

// Read content
$contents = $fm->get('data/file.pdf');

// Check existence
$fm->exists('data/file.pdf');

// Get storage path (e.g. "data/doc.pdf") for pod APIs
$path = $fm->path('doc.pdf');
```

Files under `remote_prefix` (default `data`) map to `/workspace/data/` on the pod.

## RefreshesRunPod Trait

For queued jobs that use RunPod, use the `RefreshesRunPod` trait to keep the pod alive until the prune timer fires. It updates `last_run_at` after your work completes:

```php
use ChrisThompsonTLDR\LaravelRunPod\Concerns\RefreshesRunPod;
use ChrisThompsonTLDR\LaravelRunPod\RunPod;

class PymupdfJob implements ShouldQueue
{
    use RefreshesRunPod;

    protected function runPodInstance(): string
    {
        return 'pymupdf';
    }

    public function handle(): void
    {
        $pod = RunPod::instance('pymupdf')->start();

        $this->withRunPodRefresh(function () use ($pod) {
            $response = Http::post($pod['url'] . '/extract', [...]);
            // process response
        }); // for() fires automatically in finally
    }
}
```

`withRunPodRefresh()` wraps your callback and calls `RunPod::for(static::class)` in a `finally` block, updating `last_run_at` so the prune job does not terminate the pod while work is in progress.

## Named Instances

Configure multiple pod/serverless instances in `config/runpod.php` under `instances`. Each has its own state file, prune schedule, and can override `image_name`, `network_volume_id`, etc.

```php
'instances' => [
    'pymupdf' => [
        'type' => 'pod',
        'prune_schedule' => 'everyFiveMinutes',
        'pod' => [
            'image_name' => env('RUNPOD_PYMUPDF_IMAGE'),
            'network_volume_id' => env('RUNPOD_PYMUPDF_NETWORK_VOLUME_ID'),
            'gpu_count' => 0,
            'name' => 'eyejay-pymupdf',
            // ...
        ],
    ],
    'docling' => [
        'type' => 'pod',
        'prune_schedule' => 'everyFiveMinutes',
        'pod' => [
            'image_name' => env('RUNPOD_DOCLING_IMAGE'),
            'gpu_count' => 1,
            'name' => 'eyejay-docling',
            // ...
        ],
    ],
],
```

Usage:

```bash
php artisan runpod:start pymupdf
php artisan runpod:start docling
php artisan runpod:prune pymupdf
```

In code:

```php
RunPod::instance('pymupdf')->start();
RunPod::instance('docling')->start();
```

## Merged Pod Config

To get the merged pod config for an instance (base + instance overrides):

```php
$config = RunPod::mergedPodConfigForInstance('pymupdf');
$inactivityMinutes = $config['inactivity_minutes'];
```
