# Local Docker Mode

When developing with Laravel Sail or Docker Compose, you can run RunPod pod images locally instead of on RunPod cloud. This avoids API costs and speeds up iteration.

## Overview

In local mode:

- No RunPod API calls (create, get, terminate)
- URL comes from config (`local_url`) instead of RunPod proxy
- Sync operations (`syncFrom`, `syncAll`, `ensure`) are no-ops—files are shared via bind mount
- Disk uses Laravel `config/filesystems.php` (e.g. `runpod_local`) instead of S3

## Add the Pod to Compose

Add your pod as a service in `compose.yaml` or `docker-compose.yaml`. Use the same image and environment as your RunPod config.

**Example (Laravel Sail):**

```yaml
services:
  example:
    image: nginx:alpine
    ports:
      - "80:80"
```

For containers that need file access, add a bind mount: `./storage/app/runpod:/workspace` maps the instance's `load_path` to the container's `volume_mount_path`.

## Configuration

In `config/runpod.php`, set `type` to `local` and `local_url` for the instance:

```php
'instances' => [
    'example' => [
        'type' => 'local',
        'local_url' => env('RUNPOD_EXAMPLE_LOCAL_URL', 'http://example:80'),
        'local_disk' => 'runpod_local',
        'image_name' => 'nginx:alpine',
        'name' => 'runpod-example',
        'ports' => '80/http',
    ],
],
```

- **local_url**: Use `http://example:80` when Laravel runs in the same compose network (e.g. Sail). Use `http://localhost:80` when Laravel runs on the host.
- **local_disk**: When local, use a disk whose root matches the instance's `load_path`. Define it in `config/filesystems.php`:

```php
'runpod_local' => [
    'driver' => 'local',
    'root' => storage_path('app/runpod'),
    'visibility' => 'private',
],
```

Then set `'local_disk' => 'runpod_local'` for the instance when in local mode.

## Environment Variables

```env
RUNPOD_EXAMPLE_LOCAL_URL=http://example:80
```

When `type` is `local`, you do not need `RUNPOD_API_KEY` or S3 credentials.

## Commands

| Command | Local mode behavior |
|---------|----------------------|
| `runpod:start` | Returns local URL immediately, no API call |
| `runpod:sync` | No-op with message: "Instance X is in local mode; sync skipped (files shared via bind mount)." |
| `runpod:prune` | No-op |
| `runpod:flush` | Skips cloud API; clears state and stats only |
| `runpod:list` | Shows "local (http://...)" in status |
| `runpod:inspect` | Shows local URL and disk |
| `runpod:stats` | Writes minimal stats (url, status) |
| `runpod:dashboard` | Displays local instance with URL |

## Mixed Instances

You can have some instances local and others cloud. Set `'type' => 'local'` for instances you run locally (or `env('RUNPOD_TYPE', 'pod')` if you prefer env-driven). Cloud instances still require `RUNPOD_API_KEY` and S3 credentials.
