<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Console;

use Carbon\Carbon;
use ChrisThompsonTLDR\LaravelRunPod\Exceptions\RunPodApiKeyNotConfiguredException;
use ChrisThompsonTLDR\LaravelRunPod\RunPod;
use ChrisThompsonTLDR\LaravelRunPod\RunPodPodClient;
use Illuminate\Console\Command;

class ListCommand extends Command
{
    protected $signature = 'runpod:list';

    protected $description = 'List configured RunPod instances (pods/serverless)';

    public function handle(): int
    {
        try {
            $client = $this->laravel->make(RunPodPodClient::class);
        } catch (RunPodApiKeyNotConfiguredException) {
            $client = null;
        }
        $instances = config('runpod.instances', []);

        if (empty($instances)) {
            $this->warn('No instances configured. Add entries under config/runpod.php "instances".');

            return self::SUCCESS;
        }

        $basePod = config('runpod.pod', []);
        $rows = [];
        foreach ($instances as $name => $config) {
            $type = $config['type'] ?? 'pod';
            $prune = $config['prune_schedule'] ?? ($type === 'pod' ? config('runpod.prune_schedule', 'everyFiveMinutes') : '-');
            $podConfig = array_merge($basePod, $config['pod'] ?? []);
            $image = $podConfig['image_name'] ?? '-';
            $status = $type === 'pod' && $client ? $this->podStatus($name, $client) : '-';

            $rows[] = [
                $name,
                $type,
                $prune,
                $image,
                $status,
            ];
        }

        $this->table(
            ['Instance', 'Type', 'Prune Schedule', 'Image', 'Status'],
            $rows
        );

        $this->newLine();
        $this->line('Start: <info>php artisan runpod:start [instance]</info>');
        $this->line('Prune: <info>php artisan runpod:prune [instance]</info>');

        return self::SUCCESS;
    }

    protected function podStatus(string $instance, RunPodPodClient $client): string
    {
        $statePath = $this->resolveStatePath($instance);
        if (! $statePath || ! is_file($statePath)) {
            return '-';
        }

        $state = json_decode((string) file_get_contents($statePath), true);
        if (! is_array($state) || empty($state['pod_id'])) {
            return '-';
        }

        $pod = $client->getPod($state['pod_id']);
        if (! $pod || ($pod['desiredStatus'] ?? '') !== 'RUNNING') {
            return 'stopped';
        }

        $lastRunAt = $state['last_run_at'] ?? null;
        $podConfig = RunPod::mergedPodConfigForInstance($instance);
        $inactivityMinutes = (int) ($podConfig['inactivity_minutes'] ?? config('runpod.pod.inactivity_minutes', 2));

        $startTime = $this->formatStartTime($pod);
        $shutdownIn = $this->formatShutdownIn($lastRunAt, $inactivityMinutes);

        return trim("{$startTime}  {$shutdownIn}");
    }

    protected function formatStartTime(array $pod): string
    {
        $createdAt = $pod['createdAt'] ?? $pod['runtime']['startedAt'] ?? null;
        if (! $createdAt) {
            return '';
        }

        try {
            $dt = Carbon::parse($createdAt);

            return $dt->format('H:i:s');
        } catch (\Throwable) {
            return '';
        }
    }

    protected function formatShutdownIn(?string $lastRunAt, int $inactivityMinutes): string
    {
        if (! $lastRunAt) {
            return '';
        }

        try {
            $shutdownAt = Carbon::parse($lastRunAt)->addMinutes($inactivityMinutes);
            $seconds = (int) max(0, $shutdownAt->diffInSeconds(now(), false));

            return sprintf('%s to shutdown', gmdate('H:i:s', $seconds));
        } catch (\Throwable) {
            return '';
        }
    }

    protected function resolveStatePath(string $instance): ?string
    {
        $config = config("runpod.instances.{$instance}", []);
        if (! empty($config['state_file'])) {
            return $config['state_file'];
        }
        $base = config('runpod.state_file', storage_path('app/runpod-pod-state.json'));
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $instance);

        if (str_ends_with($base, '.json')) {
            return preg_replace('/\.json$/', "-{$safe}.json", $base);
        }

        return $base.'.'.$safe;
    }
}
