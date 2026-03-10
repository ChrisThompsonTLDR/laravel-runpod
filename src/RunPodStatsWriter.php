<?php

namespace ChrisThompsonTLDR\LaravelRunPod;

use Carbon\Carbon;

class RunPodStatsWriter
{
    /**
     * @param  int|null  $inactivityMinutes  Default: 2
     */
    public function write(string $instance, array $pod, ?array $telemetry, ?string $lastRunAt, ?int $inactivityMinutes = null): void
    {
        $inactivityMinutes = $inactivityMinutes ?? 2;
        $timeUntilKill = $this->computeTimeUntilKill($lastRunAt, $inactivityMinutes);

        $data = [
            'instance' => $instance,
            'updated_at' => now()->toIso8601String(),
            'pod' => $pod,
            'telemetry' => $telemetry,
            'time_until_kill' => $timeUntilKill,
            'last_run_at' => $lastRunAt,
            'inactivity_minutes' => $inactivityMinutes,
        ];

        $path = $this->pathForInstance($instance);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tmp = $path.'.tmp.'.getmypid().'.'.uniqid('', true);
        file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        rename($tmp, $path);
    }

    public function flush(?string $instance = null): void
    {
        if ($instance !== null) {
            $path = $this->pathForInstance($instance);
            if (file_exists($path)) {
                unlink($path);
            }

            return;
        }

        foreach (array_keys(config('runpod.instances', [])) as $inst) {
            $path = $this->pathForInstance($inst);
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function read(?string $instance = null): ?array
    {
        if ($instance === null) {
            return null;
        }

        $path = $this->pathForInstance($instance);
        if (! file_exists($path)) {
            return null;
        }
        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    protected function computeTimeUntilKill(?string $lastRunAt, int $inactivityMinutes): string
    {
        if (! $lastRunAt) {
            return '00:00:00';
        }

        try {
            $lastRun = Carbon::parse($lastRunAt);
            $killAt = $lastRun->copy()->addMinutes($inactivityMinutes);
            $now = now();

            if ($now->gte($killAt)) {
                return '00:00:00';
            }

            $diffSeconds = $now->diffInSeconds($killAt);
            $hours = (int) floor($diffSeconds / 3600);
            $minutes = (int) floor(($diffSeconds % 3600) / 60);
            $seconds = (int) ($diffSeconds % 60);

            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        } catch (\Throwable) {
            return '00:00:00';
        }
    }

    protected function pathForInstance(string $instance): string
    {
        $config = config("runpod.instances.{$instance}", []);
        if (! empty($config['stats_file'])) {
            $path = $config['stats_file'];

            return str_starts_with($path, '/') ? $path : base_path($path);
        }

        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $instance);

        return storage_path("app/runpod-stats-{$safe}.json");
    }
}
