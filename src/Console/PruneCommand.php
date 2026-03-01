<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Console;

use ChrisThompsonTLDR\LaravelRunPod\RunPodClient;
use ChrisThompsonTLDR\LaravelRunPod\RunPodPodManager;
use ChrisThompsonTLDR\LaravelRunPod\RunPodStatsWriter;
use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature = 'runpod:prune {instance? : Instance name (e.g. pymupdf). Omit to prune default.}';

    protected $description = 'Terminate RunPod pod after inactivity threshold';

    public function handle(RunPodPodManager $manager, RunPodClient $client, RunPodStatsWriter $statsWriter): int
    {
        $instance = $this->argument('instance');
        $statePath = $this->resolveStatePath($instance);
        $manager->setStatePath($statePath);
        $manager->setInstanceName($instance ?? 'default');

        $instances = config('runpod.instances', []);
        if ($instance && isset($instances[$instance])) {
            $manager->configure($instances[$instance]['pod'] ?? []);
        }

        $serverlessStopped = $this->confirmServerlessStopped($client);
        if (! $serverlessStopped) {
            $this->warn('Serverless workers still running; skipping stats flush.');
        }

        if ($manager->pruneIfInactive()) {
            $this->info('Pod terminated due to inactivity.');
            if ($serverlessStopped) {
                $statsWriter->flush($instance ?? 'default');
            }

            return self::SUCCESS;
        }

        $this->info('Pod still active or no pod to prune.');

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
            $base = config('runpod.state_file', storage_path('app/runpod-pod-state.json'));
            $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $instance);
            if (str_ends_with($base, '.json')) {
                return preg_replace('/\.json$/', "-{$safe}.json", $base);
            }

            return $base.'.'.$safe;
        }

        return config('runpod.state_file', storage_path('app/runpod-pod-state.json'));
    }
}
