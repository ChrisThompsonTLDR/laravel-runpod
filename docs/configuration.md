# Configuration

The `config/runpod.php` file controls all RunPod behavior. Publish it with:

```bash
php artisan vendor:publish --tag=laravel-runpod-config
```

## Top-Level Options

- **disk** (string, default: `runpod`) — Laravel filesystem disk name for S3 storage
- **load_path** (string, default: `storage_path('app/runpod')`) — Local directory to sync files from
- **remote_prefix** (string, default: `data`) — S3 prefix; maps to `/workspace/data/` on pods
- **api_key** (string, default: `env('RUNPOD_API_KEY')`) — RunPod REST API key
- **state_file** (string, default: `storage_path('app/runpod-pod-state.json')`) — Base path for pod state JSON
- **stats_file** (string, default: `storage_path('app/runpod-stats.json')`) — Path for dashboard stats JSON
- **prune_schedule** (string, default: `everyFiveMinutes`) — Default prune frequency when no instances

## S3 Configuration

```php
's3' => [
    'key' => env('RUNPOD_S3_ACCESS_KEY'),
    'secret' => env('RUNPOD_S3_SECRET_KEY'),
    'region' => env('RUNPOD_S3_REGION', 'US-MD-1'),
    'bucket' => env('RUNPOD_NETWORK_VOLUME_ID'),  // Network volume ID = S3 bucket
    'endpoint' => env('RUNPOD_S3_ENDPOINT', 'https://s3api-us-md-1.runpod.io'),
],
```

The `runpod` disk is only registered when `key`, `secret`, and `bucket` are all non-empty. Use your network volume ID as the bucket.

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

## Default Pod Config

Used as fallback when instance config omits keys:

```php
'pod' => [
    'inactivity_minutes' => 2,
    'gpu_type_id' => 'NVIDIA GeForce RTX 4090',
    'gpu_count' => 0,
    'volume_in_gb' => 50,
    'container_disk_in_gb' => 50,
    'min_vcpu_count' => 2,
    'min_memory_in_gb' => 15,
],
```

## Instances

Named pod/serverless instances live under `instances`:

```php
'instances' => [
    'pymupdf' => [
        'type' => 'pod',
        'prune_schedule' => 'everyFiveMinutes',
        'pod' => [
            'inactivity_minutes' => 2,
            'gpu_count' => 0,
            'image_name' => env('RUNPOD_PYMUPDF_IMAGE', env('RUNPOD_POD_IMAGE')),
            'network_volume_id' => env('RUNPOD_PYMUPDF_NETWORK_VOLUME_ID', env('RUNPOD_NETWORK_VOLUME_ID')),
            'name' => env('RUNPOD_PYMUPDF_POD_NAME', 'eyejay-pymupdf'),
            'ports' => '8000/http',
            'volume_mount_path' => '/workspace',
            'env' => [
                ['key' => 'PYMUPDF_DATA_DIR', 'value' => '/workspace'],
            ],
        ],
    ],
],
```

### Instance Options

- **type** (string) — `pod` or `serverless`
- **prune_schedule** (string) — Laravel schedule method (e.g. `everyFiveMinutes`)
- **state_file** (string) — Override state file path for this instance
- **load_path** (string) — Override load path for file sync
- **remote_prefix** (string) — Override S3 prefix
- **pod** (array) — Pod creation parameters (merged with `config/runpod.pod`)

### Pod Creation Parameters

- **image_name** (string) — Docker image (required)
- **network_volume_id** (string) — Attach this network volume
- **name** (string) — Pod name in RunPod
- **gpu_count** (int) — Number of GPUs (0 = CPU)
- **gpu_type_id** (string) — GPU type when gpu_count > 0
- **volume_in_gb** (int) — Ephemeral volume size
- **container_disk_in_gb** (int) — Container disk size
- **volume_mount_path** (string) — Mount path (e.g. `/workspace`)
- **ports** (string) — Comma-separated, e.g. `8000/http,22/tcp`
- **env** (array) — `[['key' => 'X', 'value' => 'Y'], ...]`
- **inactivity_minutes** (int) — Minutes idle before prune
- **data_center_ids** (array) — Preferred datacenters
- **health_path** (string) — Health check path (e.g. `/health`)

### State and Stats Paths

Per-instance state files default to `{state_file}-{instance}.json`. Stats are written per instance to the shared `stats_file` with instance keys.
