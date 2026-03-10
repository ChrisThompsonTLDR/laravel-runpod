<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Console;

use ChrisThompsonTLDR\LaravelRunPod\Facades\RunPod;
use Illuminate\Console\Command;

class InspectCommand extends Command
{
    protected $signature = 'runpod:inspect
        {instance : Instance name (e.g. example)}';

    protected $description = 'Inspect a RunPod instance (pod details from API, including network volume)';

    public function handle(): int
    {
        $instance = $this->argument('instance');

        $instances = config('runpod.instances', []);
        if (! isset($instances[$instance])) {
            $this->error("Unknown instance: {$instance}. Configure in config/runpod.php under 'instances'.");

            return self::FAILURE;
        }

        $config = $instances[$instance];
        $pod = RunPod::instance($instance)->for('runpod:inspect')->pod();

        if (! $pod) {
            $this->warn('No pod running for this instance. Run: php artisan runpod:start '.$instance);

            return self::SUCCESS;
        }

        $isLocal = ($config['type'] ?? 'pod') === 'local';

        if ($isLocal) {
            $this->info('Pod: local');
            $this->line('  Mode: local (Docker)');
            $this->line('  URL: '.($config['local_url'] ?? '-'));
            $this->line('  Disk: '.($config['local_disk'] ?? 'local'));

            return self::SUCCESS;
        }

        $podConfig = $config;
        $expectedVolumeId = $podConfig['network_volume_id'] ?? null;
        $actualVolumeId = $pod['networkVolumeId'] ?? null;
        $hasStorage = ! empty($actualVolumeId);
        $matches = $hasStorage && $expectedVolumeId && $actualVolumeId === $expectedVolumeId;

        $this->info('Pod: '.($pod['name'] ?? $pod['id'] ?? 'unknown'));
        $this->line('  ID: '.($pod['id'] ?? '-'));
        $this->line('  Status: '.($pod['desiredStatus'] ?? '-'));
        $this->line('  Network volume: '.($hasStorage ? $actualVolumeId : 'none'));
        if ($expectedVolumeId) {
            $this->line('  Expected volume: '.$expectedVolumeId);
            $this->line('  Match: '.($matches ? 'yes' : 'no'));
        }

        return self::SUCCESS;
    }
}
