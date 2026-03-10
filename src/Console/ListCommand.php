<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Console;

use Carbon\Carbon;
use ChrisThompsonTLDR\LaravelRunPod\Exceptions\RunPodApiKeyNotConfiguredException;
use ChrisThompsonTLDR\LaravelRunPod\RunPod;
use ChrisThompsonTLDR\LaravelRunPod\RunPodClient;
use ChrisThompsonTLDR\LaravelRunPod\RunPodPodClient;
use Illuminate\Console\Command;

class ListCommand extends Command
{
    protected $signature = 'runpod:list';

    protected $description = 'List configured RunPod instances (pods/serverless)';

    public function handle(): int
    {
        try {
            $podClient = $this->laravel->make(RunPodPodClient::class);
            $restClient = $this->laravel->make(RunPodClient::class);
        } catch (RunPodApiKeyNotConfiguredException) {
            $podClient = null;
            $restClient = null;
        }
        $instances = config('runpod.instances', []);

        if (empty($instances)) {
            $this->warn('No instances configured. Add entries under config/runpod.php "instances".');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($instances as $name => $config) {
            $type = $config['type'] ?? 'pod';
            $prune = $config['prune_schedule'] ?? ($type === 'pod' ? 'everyFiveMinutes' : '-');
            $image = $config['image_name'] ?? '-';
            $isLocal = ($config['type'] ?? 'pod') === 'local';
            $status = $isLocal
                ? 'local ('.($config['local_url'] ?? '-').')'
                : ($type === 'pod' && $podClient ? $this->podStatus($name, $podClient) : '-');

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

        $allPods = $restClient ? $this->getAllPodsWithTracking($restClient) : [];
        if (! empty($allPods)) {
            $this->newLine();
            $this->line('All pods (from RunPod API):');
            $this->table(
                ['Name', 'ID', 'Status', 'Tracked'],
                $allPods
            );
        }

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
        $inactivityMinutes = (int) ($podConfig['inactivity_minutes'] ?? 2);

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

        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $instance);

        return storage_path("app/runpod-pod-state-{$safe}.json");
    }

    /**
     * All pods from listPods(), with Tracked (Yes/No) per instance config.
     *
     * @return array<int, array{string, string, string, string}>
     */
    protected function getAllPodsWithTracking(RunPodClient $client): array
    {
        $pods = $client->listPods();
        if (empty($pods)) {
            return [];
        }

        $instanceNames = $this->instancePodNames();
        $statePodIds = $this->statePodIdsByInstance();

        $rows = [];
        foreach ($pods as $pod) {
            $id = $pod['id'] ?? null;
            $name = $pod['name'] ?? '';
            $status = $pod['desiredStatus'] ?? '-';
            if (! $id) {
                continue;
            }

            $tracked = null;
            foreach ($instanceNames as $instance => $podName) {
                if ($name !== $podName) {
                    continue;
                }
                $stateId = $statePodIds[$instance] ?? null;
                $tracked = ($id === $stateId) ? 'Yes' : 'No (orphan)';
                break;
            }
            if ($tracked === null) {
                continue;
            }
            $rows[] = [$name ?: '(no name)', $id, $status, $tracked];
        }

        return $rows;
    }

    /** @return array<string, string> instance => pod name */
    protected function instancePodNames(): array
    {
        $out = [];
        foreach (config('runpod.instances', []) as $name => $config) {
            if (($config['type'] ?? 'pod') !== 'pod') {
                continue;
            }
            $podName = $config['name'] ?? null;
            if ($podName) {
                $out[$name] = $podName;
            }
        }

        return $out;
    }

    /** @return array<string, string> instance => pod_id from state */
    protected function statePodIdsByInstance(): array
    {
        $out = [];
        foreach (array_keys(config('runpod.instances', [])) as $instance) {
            $path = $this->resolveStatePath($instance);
            if (! $path || ! is_file($path)) {
                continue;
            }
            $state = json_decode((string) file_get_contents($path), true);
            if (is_array($state) && ! empty($state['pod_id'])) {
                $out[$instance] = $state['pod_id'];
            }
        }

        return $out;
    }
}
