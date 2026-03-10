# Dashboards

## Web Dashboard (Livewire + Flux)

When Livewire and Flux are installed, the RunPod dashboard is available at:

```
/runpod/dashboard
/runpod/dashboard/{instance}
```

Example: `/runpod/dashboard/example`

The dashboard is a Livewire v4 Single Page Component (SPC). It reads from the stats file and polls every 15 seconds. It shows pod status, specs, telemetry (CPU, memory, GPU), and time until prune.

### Requirements

- **Layout** — The dashboard uses your app's `layouts.app` layout. Create it with:

  ```bash
  php artisan livewire:layout
  ```

  Ensure the layout includes `{{ $slot }}`, `@livewireStyles`, and `@livewireScripts`.

- **Flux** — Add `@fluxAppearance` and `@fluxScripts` to your layout, or the dashboard will include them in its content.

### Authorization (Gate)

The dashboard is protected from public access like Telescope and Pulse. A `viewRunpod` gate is registered in `RunPodServiceProvider`:

- **Local environment** — Access allowed without authentication
- **Production** — Requires an authenticated user (override in `AuthServiceProvider`)

To customize, define the gate in `App\Providers\AuthServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewRunpod', function (?User $user = null) {
    if (app()->environment('local')) {
        return true;
    }
    return $user?->email === 'admin@example.com';
});
```

### Configuration

Dashboard middleware is configurable in `config/runpod.php`:

```php
'dashboard' => [
    'middleware' => ['web', 'can:viewRunpod'],
],
```

### Customize Views

```bash
php artisan vendor:publish --tag=laravel-runpod-dashboard
```

This publishes views to `resources/views/vendor/runpod/`. The dashboard component lives at `livewire/runpod/runpod-dashboard.blade.php`.

## Terminal Dashboard

A live-updating terminal dashboard is available with Termwind Live:

```bash
composer require nunomaduro/termwind xico2k/termwind-plugin-live

php artisan runpod:dashboard example
php artisan runpod:dashboard example --refresh=5
```

The terminal shows pod name, status, cost/hr, time until prune, specs (vCPUs, memory, GPU, disk), and telemetry (CPU, memory, GPU utilization, temperature).

## Stats File

Both dashboards read from per-instance stats files (default `storage/app/runpod-stats-{instance}.json`). The stats are written by:

- `RunPodPodManager` when a pod is ensured or details are fetched
- `runpod:stats` (runs every 2 minutes via scheduler)

If no data appears, run:

```bash
php artisan runpod:start example
php artisan runpod:stats example
```
