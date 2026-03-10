# Artisan Commands

## runpod:sync

Sync files from the local load path to RunPod network volume storage.

```bash
# Sync entire load path
php artisan runpod:sync

# Sync a specific file or directory
php artisan runpod:sync --path=document.pdf
php artisan runpod:sync --path=subdir/
```

Paths must be within the configured `load_path`. Path traversal (`..`) is rejected.

## runpod:start

Ensure a RunPod instance is running. Creates and waits for the pod if needed.

```bash
php artisan runpod:start example
php artisan runpod:start example --nickname=runpod:start
php artisan runpod:start example --show-error
```

| Option | Default | Description |
|--------|---------|-------------|
| `--nickname` | `runpod:start` | Nickname for last_run_at tracking |
| `--show-error` | - | Show RunPod API error details on failure |

## runpod:list

List configured instances and their status.

```bash
php artisan runpod:list
```

Shows instance name, type, prune schedule, image, and status (running/stopped, time until shutdown). Also lists all pods from the RunPod API with tracked/orphan status.

## runpod:prune

Terminate a pod after the inactivity threshold.

```bash
# Prune default instance
php artisan runpod:prune

# Prune specific instance
php artisan runpod:prune example
```

Compares `last_run_at` to `inactivity_minutes`. If idle long enough, terminates the pod. Also terminates orphaned pods (same name but not in state file).

## runpod:guardrails

Refresh or clear the guardrails usage cache.

```bash
# Refresh cache (runs on schedule)
php artisan runpod:guardrails

# Clear cache without refreshing
php artisan runpod:guardrails --clear
```

## runpod:stats

Refresh the stats file used by dashboards. Runs on schedule every 2 minutes.

```bash
# Refresh all pod instances
php artisan runpod:stats

# Refresh specific instance
php artisan runpod:stats example
```

## runpod:dashboard

Live terminal dashboard (requires Termwind Live).

```bash
composer require nunomaduro/termwind xico2k/termwind-plugin-live

php artisan runpod:dashboard example
php artisan runpod:dashboard example --refresh=5
```

| Option | Default | Description |
|--------|---------|-------------|
| `--refresh` | `5` | Refresh interval in seconds |

## runpod:flush

Delete all pods and serverless endpoints. Clears state files, stats, and guardrails cache.

```bash
php artisan runpod:flush
php artisan runpod:flush --force
```

`--force` skips the confirmation prompt.

## runpod:inspect

Inspect a RunPod instance: pod details from the API, including network volume.

```bash
php artisan runpod:inspect example
```

## Scheduled Commands

The service provider registers these schedules automatically:

| Command | Schedule | Description |
|---------|----------|-------------|
| `runpod:prune` | Per instance (prune_schedule) | Terminate inactive pods |
| `runpod:guardrails` | everyFifteenMinutes | Refresh usage cache |
| `runpod:stats` | everyTwoMinutes | Refresh dashboard stats |

Ensure the scheduler is running:

```bash
php artisan schedule:work
```
