# Configuration

The `config/runpod.php` file controls all RunPod behavior. Publish it with:

```bash
php artisan vendor:publish --tag=laravel-runpod-config
```

## Top-Level Options

- **api_key** (string, default: `env('RUNPOD_API_KEY')`) — RunPod REST API key

## Dashboard

```php
'dashboard' => [
    'middleware' => ['web', 'can:viewRunpod'],
],
```

- **middleware** (array) — Middleware applied to the web dashboard routes. Default includes `web` and `can:viewRunpod`. The `viewRunpod` gate is registered by `RunPodServiceProvider`; override it in `AuthServiceProvider` to customize access (e.g. allow only admins in production).

## Disk Configuration (per instance)

- **local_disk** — Laravel disk name when in local mode (e.g. `runpod_local`)
- **remote_disk** — S3 config for cloud storage. Keys: `disk_name`, `prefix` (folder under volume root, default `data`; maps to `/workspace/{prefix}/` on pods), `key`, `secret`, `region`, `bucket`, `endpoint`:

```php
'remote_disk' => [
    'disk_name' => 'runpod',
    'prefix' => 'data',
    'key' => env('RUNPOD_S3_ACCESS_KEY'),
    'secret' => env('RUNPOD_S3_SECRET_KEY'),
    'region' => env('RUNPOD_S3_REGION', 'US-MD-1'),
    'bucket' => env('RUNPOD_NETWORK_VOLUME_ID'),
    'endpoint' => env('RUNPOD_S3_ENDPOINT', 'https://s3api-us-md-1.runpod.io'),
],
```

The disk is only registered when `key`, `secret`, and `bucket` are all non-empty. Local instances use `local_disk` and do not need `remote_disk`.

## Guardrails

```php
'guardrails' => [
    'enabled' => true,
    'cache_schedule' => 'everyFifteenMinutes',
    'limits' => [
        'pods' => [
            'pods_max' => 10,           // Total pods
            'pods_running_max' => 5,    // Running pods
        ],
        'serverless' => [
            'endpoints_max' => 5,
            'workers_total_max' => 20,
        ],
        'storage' => [
            'network_volumes_max' => 5,
            'volume_size_gb_max' => 100,
        ],
    ],
],
```

Set limits to `0` to disable that check. See [Guardrails](guardrails.md) for details.

## Instances

Named instances live under `instances`. Each has `type` (pod|serverless|local), storage config, and pod params:

```php
'instances' => [
    'example' => [
        'type' => 'pod',
        'load_path' => storage_path('app/runpod'),
        'prune_schedule' => 'everyFiveMinutes',
        'inactivity_minutes' => 2,
        'spec' => ['cpu5c-16-32', 'cpu5g-16-32'],
        'image_name' => 'nginx:alpine',
        'name' => 'runpod-example',
        'ports' => '80/http',
    ],
],
```

### Instance Options

- **type** (string) — `pod`, `serverless`, or `local`. Use `local` for local Docker (no RunPod API). See [Local Docker](local-docker.md).
- **load_path** (string) — Local directory to sync files from (e.g. `storage_path('app/runpod')`)
- **local_disk** (string) — Disk name when `type` is `local` (e.g. `runpod_local`)
- **remote_disk** (array) — S3 config: `disk_name`, `prefix`, `key`, `secret`, `region`, `bucket`, `endpoint`
- **local_url** (string) — URL for the local pod when `type` is `local` (e.g. `http://example:80` or `http://localhost:80`)
- **prune_schedule** (string) — Prune frequency for pods (e.g. `everyFiveMinutes`, default when omitted)
- **inactivity_minutes** (int) — Minutes idle before prune (default 2)
- **state_file** (string) — Path for pod state JSON (default: `storage_path('app/runpod-pod-state-{instance}.json')`)
- **endpoint_state_file** (string) — Path for serverless endpoint state JSON (default: `storage_path('app/runpod-endpoint-state-{instance}.json')`)
- **stats_file** (string) — Path for dashboard stats JSON (default: `storage_path('app/runpod-stats-{instance}.json')`)

### Pod Parameters (on instance)

- **spec** (array) — CPU specs to try, e.g. `['cpu5c-16-32', 'cpu5g-16-32']`. Use `['*']` for any flavor. For GPU: `gpu_type_id`, `gpu_count`, `volume_in_gb`.
- **image_name** (string) — Docker image (required)
- **name** (string) — Pod name in RunPod
- **ports** (string) — Comma-separated, e.g. `80/http,22/tcp`
- **network_volume_id** (string) — Attach this network volume
- **volume_mount_path** (string) — Mount path (e.g. `/workspace`)
- **env** (array) — `[['key' => 'X', 'value' => 'Y'], ...]`

### State and Stats Paths

Stats are written per instance to each instance's `stats_file`.
