<?php

namespace ChrisThompsonTLDR\LaravelRunPod;

use Carbon\Carbon;

class RunPodStatsWriter
{
    public function __construct(
        protected ?string $basePath = null
    ) {
        $this->basePath = $basePath ?? config('runpod.stats_file', storage_path('app/runpod-stats.json'));
    }

    /**
     * Write stats for an instance. Merges with existing, computes time_until_kill, writes JSON.
     */
    public function write(string $instance, array $pod, ?array $telemetry, ?string $lastRunAt): void
    {
        $inactivityMinutes = config('runpod.pod.inactivity_minutes', 2);
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

    /**
     * Flush stats file(s). Pass instance to flush one, or null to flush all.
     */
    public function flush(?string $instance = null): void
    {
        if ($instance !== null) {
            $path = $this->pathForInstance($instance);
            if (file_exists($path)) {
                unlink($path);
            }

            return;
        }

        $baseDir = dirname($this->basePath);
        $baseName = basename($this->basePath, '.json');
        $glob = $baseDir.DIRECTORY_SEPARATOR.$baseName.'*.json';

        foreach (glob($glob) as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Read stats. Pass instance to read one, or null to read default/aggregate.
     */
    public function read(?string $instance = null): ?array
    {
        if ($instance !== null) {
            $path = $this->pathForInstance($instance);
            if (! file_exists($path)) {
                return null;
            }
            $data = json_decode(file_get_contents($path), true);

            return is_array($data) ? $data : null;
        }

        if (file_exists($this->basePath)) {
            $data = json_decode(file_get_contents($this->basePath), true);

            return is_array($data) ? $data : null;
        }

        return null;
    }

    /**
     * Compute time until prune as hh:mm:ss. Returns "00:00:00" when past threshold.
     */
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

            $diffSeconds = $killAt->diffInSeconds($now);
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
        $base = $this->basePath;
        if (str_ends_with($base, '.json')) {
            $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $instance);

            return preg_replace('/\.json$/', "-{$safe}.json", $base);
        }

        return $base;
    }
}
