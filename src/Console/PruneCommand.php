<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Console;

use ChrisThompsonTLDR\LaravelRunPod\RunPodClient;
use ChrisThompsonTLDR\LaravelRunPod\RunPodPodManager;
use ChrisThompsonTLDR\LaravelRunPod\RunPodStatsWriter;
use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature = 'runpod:prune {instance? : Instance name (e.g. example). Omit to prune default.}';

    protected $description = 'Terminate RunPod pod after inactivity threshold';

    public function handle(RunPodPodManager $manager, RunPodClient $client, RunPodStatsWriter $statsWriter): int
    {
        $instance = $this->argument('instance');
        $statePath = $this->resolveStatePath($instance);
        $manager->setStatePath($statePath);
        $manager->setInstanceName($instance ?? 'default');

        $instances = config('runpod.instances', []);
        if ($instance && isset($instances[$instance])) {
            $config = $instances[$instance];
            $podConfig = $config;
            $podConfig['local'] = ($config['type'] ?? 'pod') === 'local';
            if (isset($config['local_url'])) {
                $podConfig['local_url'] = $config['local_url'];
            }
            $manager->configure($podConfig);
        }

        if ($manager->isLocal()) {
            $this->info('Instance is in local mode; prune skipped.');

            return self::SUCCESS;
        }

        $serverlessStopped = $this->confirmServerlessStopped($client);
        if (! $serverlessStopped) {
            $this->warn('Serverless workers still running; skipping stats flush.');
        }

        $didPrune = $manager->pruneIfInactive();
        if ($didPrune) {
            $this->info('Pod terminated due to inactivity.');
            if ($serverlessStopped) {
                $statsWriter->flush($instance ?? 'default');
            }
        }

        $orphaned = $this->terminateOrphanedPods($client, $instance);
        if ($orphaned > 0) {
            $this->info("Terminated {$orphaned} orphaned pod(s) (not in state file).");
        }

        if (! $didPrune && $orphaned === 0) {
            $this->info('Pod still active or no pod to prune.');
        }

        return self::SUCCESS;
    }

    protected function confirmServerlessStopped(RunPodClient $client): bool
    {
        $instances = config('runpod.instances', []);
        $hasServerless = ! empty(array_filter($instances, fn ($c) => ($c['type'] ?? 'pod') === 'serverless'));
        if (! $hasServerless) {
            return true;
        }

        $endpoints = $client->listEndpoints();
        foreach ($endpoints as $ep) {
            $workers = $ep['workers'] ?? [];
            $running = array_filter($workers, fn ($w) => ($w['status'] ?? '') === 'RUNNING');
            if (! empty($running)) {
                return false;
            }
        }

        return true;
    }

    protected function resolveStatePath(?string $instance): string
    {
        if ($instance) {
            $config = config("runpod.instances.{$instance}", []);
            if (! empty($config['state_file'])) {
                return $config['state_file'];
            }

            $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $instance);

            return storage_path("app/runpod-pod-state-{$safe}.json");
        }

        return storage_path('app/runpod-pod-state.json');
    }

    /**
     * Terminate pods that match this instance's name but aren't in our state file.
     * Catches orphaned pods from race conditions or manual creation.
     */
    protected function terminateOrphanedPods(RunPodClient $client, ?string $instance): int
    {
        if (! $instance) {
            return 0;
        }

        $config = config("runpod.instances.{$instance}", []);
        if (($config['type'] ?? 'pod') === 'local') {
            return 0;
        }
        $podName = $config['name'] ?? null;
        if (! $podName) {
            return 0;
        }

        $statePath = $this->resolveStatePath($instance);
        $statePodId = null;
        if (is_file($statePath)) {
            $state = json_decode((string) file_get_contents($statePath), true);
            $statePodId = is_array($state) ? ($state['pod_id'] ?? null) : null;
        }

        $pods = $client->listPods();
        $terminated = 0;
        foreach ($pods as $pod) {
            $id = $pod['id'] ?? null;
            $name = $pod['name'] ?? '';
            if (! $id || $name !== $podName) {
                continue;
            }
            if ($id === $statePodId) {
                continue;
            }
            if ($client->deletePod($id)) {
                $this->line("Deleted orphaned pod: {$name} ({$id})");
                $terminated++;
            }
        }

        return $terminated;
    }
}
