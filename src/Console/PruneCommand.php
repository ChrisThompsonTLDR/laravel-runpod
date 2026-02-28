<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Console;

use ChrisThompsonTLDR\LaravelRunPod\RunPodPodManager;
use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature = 'runpod:prune {instance? : Instance name (e.g. pymupdf). Omit to prune default.}';

    protected $description = 'Terminate RunPod pod after inactivity threshold';

    public function handle(RunPodPodManager $manager): int
    {
        $instance = $this->argument('instance');
        $statePath = $this->resolveStatePath($instance);
        $manager->setStatePath($statePath);

        if ($instance) {
            $manager->configure(config("runpod.instances.{$instance}.pod", []));
        }

        if ($manager->pruneIfInactive()) {
            $this->info('Pod terminated due to inactivity.');

            return self::SUCCESS;
        }

        $this->info('Pod still active or no pod to prune.');

        return self::SUCCESS;
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
