<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Console;

use ChrisThompsonTLDR\LaravelRunPod\RunPod;
use ChrisThompsonTLDR\LaravelRunPod\RunPodClient;
use Illuminate\Console\Command;

class StartCommand extends Command
{
    protected $signature = 'runpod:start
        {instance : Instance name (e.g. pymupdf)}
        {--nickname=runpod:start : Nickname for last_run_at tracking}
        {--v|verbose : Show API error details on failure}';

    protected $description = 'Ensure a RunPod instance is running (create and wait if needed)';

    public function handle(RunPod $runPod, RunPodClient $client): int
    {
        $instance = $this->argument('instance');
        $nickname = $this->option('nickname');
        $verbose = $this->option('verbose');

        $instances = config('runpod.instances', []);
        if (! isset($instances[$instance])) {
            $this->error("Unknown instance: {$instance}. Configure in config/runpod.php under 'instances'.");

            return self::FAILURE;
        }

        $this->info("Starting RunPod instance: {$instance}...");

        $pod = $runPod->for($nickname)->instance($instance)->start();

        if (! $pod || ! ($pod['url'] ?? null)) {
            $this->error('Failed to start pod. Check config/runpod.php (image_name, network_volume_id per instance) and RunPod API status.');

            if ($verbose && ($error = $client->getLastError())) {
                $this->newLine();
                $this->line('<comment>API response:</comment>');
                $this->line($error);
            }

            return self::FAILURE;
        }

        $this->info("Pod running: {$pod['url']}");

        return self::SUCCESS;
    }
}
