<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Console;

use ChrisThompsonTLDR\LaravelRunPod\Facades\RunPod;
use ChrisThompsonTLDR\LaravelRunPod\RunPodStatsWriter;
use Illuminate\Console\Command;

class StatsCommand extends Command
{
    protected $signature = 'runpod:stats
        {instance? : Instance name (e.g. pymupdf). Omit to refresh all pod instances.}';

    protected $description = 'Refresh RunPod stats file for dashboards (fetches pod + telemetry, writes to JSON)';

    public function handle(RunPodStatsWriter $statsWriter): int
    {
        $instance = $this->argument('instance');
        $instances = config('runpod.instances', []);

        if ($instance) {
            if (! isset($instances[$instance])) {
                $this->error("Unknown instance: {$instance}. Configure in config/runpod.php under 'instances'.");

                return self::FAILURE;
            }
            $toRefresh = [$instance => $instances[$instance]];
        } else {
            $toRefresh = array_filter($instances, fn ($c) => ($c['type'] ?? 'pod') === 'pod');
        }

        if (empty($toRefresh)) {
            $this->info('No pod instances to refresh.');

            return self::SUCCESS;
        }

        $refreshed = 0;
        foreach ($toRefresh as $name => $config) {
            $pod = RunPod::instance($name)->for('runpod:stats')->pod();
            if ($pod) {
                $refreshed++;
            }
        }

        $this->info("Refreshed stats for {$refreshed} instance(s).");

        return self::SUCCESS;
    }
}
