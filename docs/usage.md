# Usage

## RunPod API Client

Inject or resolve `RunPodClient` to call the RunPod REST API directly. If `RUNPOD_API_KEY` is not configured (null or empty), the client throws `RunPodApiKeyNotConfiguredException` on the first API call.

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

$runPod = app(RunPod::class)->for(ExampleJob::class);

// File operations via the configured S3 disk
$runPod->disk('runpod')->ensure($filename);

// Start a named pod instance
$pod = $runPod->instance('example')->start();
$url = $pod['url'];
```

With the facade:

```php
RunPod::for(self::class)->disk('runpod')->ensure($filename);
$pod = RunPod::instance('example')->start();
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

Files under the instance's `remote_disk.prefix` (e.g. `data`) map to `/workspace/data/` on the pod.

## RefreshesRunPod Trait

For queued jobs that use RunPod, use the `RefreshesRunPod` trait to keep the pod alive until the prune timer fires. It updates `last_run_at` after your work completes:

```php
use ChrisThompsonTLDR\LaravelRunPod\Concerns\RefreshesRunPod;
use ChrisThompsonTLDR\LaravelRunPod\RunPod;

class ExampleJob implements ShouldQueue
{
    use RefreshesRunPod;

    protected function runPodInstance(): string
    {
        return 'example';
    }

    public function handle(): void
    {
        $pod = RunPod::instance('example')->start();

        $this->withRunPodRefresh(function () use ($pod) {
            $response = Http::post($pod['url'] . '/extract', [...]);
            // process response
        }); // for() fires automatically in finally
    }
}
```

`withRunPodRefresh()` wraps your callback and calls `RunPod::for(static::class)` in a `finally` block, updating `last_run_at` so the prune job does not terminate the pod while work is in progress.

## Named Instances

Configure multiple pod/serverless instances in `config/runpod.php` under `instances`. Each has its own state file, prune schedule, `image_name`, `name`, etc.

```php
'instances' => [
    'example' => [
        'type' => 'pod',
        'prune_schedule' => 'everyFiveMinutes',
        'image_name' => 'nginx:alpine',
        'name' => 'runpod-example',
        'spec' => ['cpu5c-16-32', 'cpu5g-16-32'],
        // ...
    ],
    'docling' => [
        'type' => 'pod',
        'prune_schedule' => 'everyFiveMinutes',
        'image_name' => 'runpod/docling',
        'gpu_count' => 1,
        'name' => 'eyejay-docling',
        // ...
    ],
],
```

Usage:

```bash
php artisan runpod:start example
php artisan runpod:start docling
php artisan runpod:prune example
```

In code:

```php
RunPod::instance('example')->start();
RunPod::instance('docling')->start();
```

## Instance Config

To get the instance config (including pod params):

```php
$config = RunPod::mergedPodConfigForInstance('example');
$inactivityMinutes = $config['inactivity_minutes'];
```
