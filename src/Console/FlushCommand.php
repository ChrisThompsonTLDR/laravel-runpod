<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Console;

use ChrisThompsonTLDR\LaravelRunPod\RunPodClient;
use ChrisThompsonTLDR\LaravelRunPod\RunPodGuardrails;
use ChrisThompsonTLDR\LaravelRunPod\RunPodStatsWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class FlushCommand extends Command
{
    protected $signature = 'runpod:flush
        {--force : Skip confirmation prompt}';

    protected $description = 'Delete/terminate all RunPod pods and serverless endpoints';

    public function handle(RunPodClient $client, RunPodGuardrails $guardrails, RunPodStatsWriter $statsWriter): int
    {
        if (! $this->option('force') && ! $this->confirm('This will delete ALL pods and serverless endpoints. Continue?')) {
            return self::SUCCESS;
        }

        $this->clearRunPodQueues();

        $podsDeleted = 0;
        $endpointsDeleted = 0;

        $pods = $client->listPods();
        foreach ($pods as $pod) {
            $id = $pod['id'] ?? null;
            if (! $id) {
                continue;
            }
            $name = $pod['name'] ?? $id;
            $status = $pod['desiredStatus'] ?? null;
            if ($status === 'RUNNING') {
                $this->line("Stopping pod: {$name} ({$id})...");
                $client->stopPod($id);
                sleep(3);
            }
            if ($client->deletePod($id)) {
                $this->line("Deleted pod: {$name} ({$id})");
                $podsDeleted++;
            } else {
                $this->warn("Failed to delete pod: {$name} ({$id})");
            }
        }

        $endpoints = $client->listEndpoints();
        foreach ($endpoints as $ep) {
            $id = $ep['id'] ?? null;
            if (! $id) {
                continue;
            }
            $name = $ep['name'] ?? $id;
            if ($client->deleteEndpoint($id)) {
                $this->line("Deleted endpoint: {$name} ({$id})");
                $endpointsDeleted++;
            } else {
                $this->warn("Failed to delete endpoint: {$name} ({$id})");
            }
        }

        $this->clearStateFiles();
        $guardrails->clearCache();
        $statsWriter->flush(null);

        $this->newLine();
        $this->info("Flush complete: {$podsDeleted} pod(s), {$endpointsDeleted} endpoint(s) deleted. State, stats, and guardrails cache cleared.");

        return self::SUCCESS;
    }

    /**
     * Clear RunPod job queues so Horizon doesn't immediately re-create pods.
     */
    protected function clearRunPodQueues(): void
    {
        if (! config('queue.connections.redis')) {
            return;
        }

        foreach (array_keys(config('runpod.instances', [])) as $instance) {
            try {
                Artisan::call('queue:clear', ['redis', '--queue' => $instance, '--force' => true]);
            } catch (\Throwable) {
                // Queue may not exist
            }
        }
    }

    protected function clearStateFiles(): void
    {
        $paths = [];
        $instances = array_keys(config('runpod.instances', []));

        foreach ($instances as $instance) {
            $path = $this->resolveStatePath($instance);
            if ($path && ! in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }

        if (empty($instances)) {
            $paths[] = storage_path('app/runpod-pod-state.json');
        }

        foreach ($instances as $instance) {
            $path = $this->resolveEndpointStatePath($instance);
            if ($path && ! in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }

        foreach (array_unique($paths) as $path) {
            if (is_file($path)) {
                unlink($path);
                $this->line("Cleared state: {$path}");
            }
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

    protected function resolveEndpointStatePath(string $instance): string
    {
        $config = config("runpod.instances.{$instance}", []);
        if (! empty($config['endpoint_state_file'])) {
            $path = $config['endpoint_state_file'];

            return str_starts_with($path, '/') ? $path : base_path($path);
        }

        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $instance);

        return storage_path("app/runpod-endpoint-state-{$safe}.json");
    }
}
