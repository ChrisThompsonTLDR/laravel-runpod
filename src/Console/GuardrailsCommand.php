<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Console;

use ChrisThompsonTLDR\LaravelRunPod\RunPodGuardrails;
use Illuminate\Console\Command;

class GuardrailsCommand extends Command
{
    protected $signature = 'runpod:guardrails {--clear : Clear the usage cache without refreshing}';

    protected $description = 'Refresh guardrails usage cache (runs on schedule)';

    public function handle(RunPodGuardrails $guardrails): int
    {
        if ($this->option('clear')) {
            $guardrails->clearCache();
            $this->info('Guardrails cache cleared.');

            return self::SUCCESS;
        }

        try {
            $guardrails->clearCache();
            $guardrails->getUsage();
            $this->info('Guardrails usage cache refreshed.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Guardrails check failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
