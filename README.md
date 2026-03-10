```
  _                              _   ____              ____           _ 
 | |    __ _ _ __ __ ___   _____| | |  _ \ _   _ _ __ |  _ \ ___   __| |
 | |   / _` | '__/ _` \ \ / / _ \ | | |_) | | | | '_ \| |_) / _ \ / _` |
 | |__| (_| | | | (_| |\ V /  __/ | |  _ <| |_| | | | |  __/ (_) | (_| |
 |_____\__,_|_|  \__,_| \_/ \___|_| |_| \_\\__,_|_| |_|_|   \___/ \__,_|
```

# Laravel RunPod

Laravel integration for the [RunPod REST API](https://rest.runpod.io/v1) and S3-compatible network volume storage.

**[Documentation](https://christhompsontldr.github.io/laravel-runpod/)**

## Requirements

- PHP 8.3+
- Laravel 11 or 12

## Installation

```bash
composer require christhompsontldr/laravel-runpod
php artisan vendor:publish --tag=laravel-runpod-config
```

Add to `.env`: `RUNPOD_API_KEY`, `RUNPOD_S3_ACCESS_KEY`, `RUNPOD_S3_SECRET_KEY`, `RUNPOD_S3_ENDPOINT`, `RUNPOD_S3_REGION`, `RUNPOD_NETWORK_VOLUME_ID`. See [Configuration](https://christhompsontldr.github.io/laravel-runpod/configuration.html).

## Quick Start

```php
use ChrisThompsonTLDR\LaravelRunPod\RunPod;

$pod = RunPod::instance('example')->start();
$url = $pod['url'];

// File ops (disk chosen from instance config)
RunPod::instance('example')->disk()->ensure('file.pdf');
```

Event listeners can start a pod with one call: `RunPod::instance('example')->start()`.

## Artisan Commands

| Command | Description |
|---------|-------------|
| `runpod:sync` | Sync files from load path to RunPod storage |
| `runpod:start {instance}` | Ensure pod is running |
| `runpod:list` | List instances and status |
| `runpod:prune {instance?}` | Terminate inactive pods |
| `runpod:guardrails` | Refresh usage cache |
| `runpod:stats {instance?}` | Refresh dashboard stats |
| `runpod:dashboard {instance}` | Terminal dashboard (Termwind Live) |
| `runpod:flush --force` | Delete all pods and endpoints |
| `runpod:inspect {instance}` | Inspect pod details |

## Local Docker

Run any container locally (Sail/Compose)—e.g. `nginx:alpine`—by setting `type` to `local` and `local_url` for an instance. No API calls or S3; files shared via bind mount. See [Local Docker](https://christhompsontldr.github.io/laravel-runpod/local-docker.html).

## Web Dashboard

When Livewire and Flux are installed: `/runpod/dashboard/{instance?}`. Protected by `viewRunpod` gate. `php artisan livewire:layout` for layout; `php artisan vendor:publish --tag=laravel-runpod-dashboard` to customize views.

## RefreshesRunPod Trait

```php
use ChrisThompsonTLDR\LaravelRunPod\Concerns\RefreshesRunPod;

class ExampleJob implements ShouldQueue
{
    use RefreshesRunPod;

    protected function runPodInstance(): string { return 'example'; }

    public function handle(): void
    {
        $pod = RunPod::instance('example')->start();
        $this->withRunPodRefresh(fn () => Http::post($pod['url'].'/extract', [...]));
    }
}
```

## Guardrails

Configure limits in `config/runpod.php` under `guardrails.limits`. When exceeded: `GuardrailsExceededException` and `GuardrailsTripped` event.

## License

MIT
