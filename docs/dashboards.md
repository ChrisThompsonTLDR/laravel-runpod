# Dashboards

## Web Dashboard (Livewire + Flux)

When Livewire and Flux are installed, the RunPod dashboard is available at:

```
/runpod/dashboard
/runpod/dashboard/{instance}
```

Example: `/runpod/dashboard/pymupdf`

The dashboard reads from the stats file and polls every 15 seconds. It shows pod status, specs, telemetry (CPU, memory, GPU), and time until prune.

### Customize Views

```bash
php artisan vendor:publish --tag=laravel-runpod-dashboard
```

This publishes views to `resources/views/vendor/runpod/`.

## Terminal Dashboard

A live-updating terminal dashboard is available with Termwind Live:

```bash
composer require nunomaduro/termwind xico2k/termwind-plugin-live

php artisan runpod:dashboard pymupdf
php artisan runpod:dashboard pymupdf --refresh=5
```

The terminal shows pod name, status, cost/hr, time until prune, specs (vCPUs, memory, GPU, disk), and telemetry (CPU, memory, GPU utilization, temperature).

## Stats File

Both dashboards read from the stats file (`config/runpod.stats_file`, default `storage/app/runpod-stats.json`). The stats are written by:

- `RunPodPodManager` when a pod is ensured or details are fetched
- `runpod:stats` (runs every 2 minutes via scheduler)

If no data appears, run:

```bash
php artisan runpod:start pymupdf
php artisan runpod:stats pymupdf
```
