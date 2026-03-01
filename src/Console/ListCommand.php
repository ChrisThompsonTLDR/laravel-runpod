<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Console;

use Illuminate\Console\Command;

class ListCommand extends Command
{
    protected $signature = 'runpod:list';

    protected $description = 'List configured RunPod instances (pods/serverless)';

    public function handle(): int
    {
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

            $rows[] = [
                $name,
                $type,
                $prune,
                $image,
            ];
        }

        $this->table(
            ['Instance', 'Type', 'Prune Schedule', 'Image'],
            $rows
        );

        $this->newLine();
        $this->line('Start: <info>php artisan runpod:start &lt;instance&gt;</info>');
        $this->line('Prune: <info>php artisan runpod:prune [instance]</info>');

        return self::SUCCESS;
    }
}
