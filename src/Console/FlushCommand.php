<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Console;

use ChrisThompsonTLDR\LaravelRunPod\RunPodClient;
use ChrisThompsonTLDR\LaravelRunPod\RunPodGuardrails;
use ChrisThompsonTLDR\LaravelRunPod\RunPodStatsWriter;
use Illuminate\Console\Command;

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

    protected function clearStateFiles(): void
    {
        $base = config('runpod.state_file', storage_path('app/runpod-pod-state.json'));
        $paths = [$base];

        foreach (array_keys(config('runpod.instances', [])) as $instance) {
            $path = $this->resolveStatePath($instance);
            if ($path && ! in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }

        foreach ($paths as $path) {
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
        $base = config('runpod.state_file', storage_path('app/runpod-pod-state.json'));
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $instance);
        if (str_ends_with($base, '.json')) {
            return preg_replace('/\.json$/', "-{$safe}.json", $base);
        }

        return $base.'.'.$safe;
    }
}
