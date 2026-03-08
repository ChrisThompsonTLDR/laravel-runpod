<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Console;

use ChrisThompsonTLDR\LaravelRunPod\Exceptions\RunPodApiKeyNotConfiguredException;
use ChrisThompsonTLDR\LaravelRunPod\RunPodClient;
use ChrisThompsonTLDR\LaravelRunPod\RunPodPodClient;
use Illuminate\Console\Command;

class DeployEndpointCommand extends Command
{
    protected $signature = 'runpod:deploy-endpoint
                            {--config= : Path to serverless config JSON (template + endpoint)}
                            {--instance= : Use serverless_config_path from this instance}
                            {--force : Recreate template/endpoint if they exist}';

    protected $description = 'Deploy a serverless endpoint from a config JSON (creates template + endpoint)';

    public function handle(): int
    {
        try {
            $client = $this->laravel->make(RunPodClient::class);
            $podClient = $this->laravel->make(RunPodPodClient::class);
        } catch (RunPodApiKeyNotConfiguredException) {
            $this->error('RUNPOD_API_KEY is not configured.');

            return self::FAILURE;
        }

        $configPath = $this->resolveConfigPath();
        if (! $configPath || ! is_file($configPath)) {
            $this->error('Config file not found. Use --config=path or --instance=name with serverless_config_path.');

            return self::FAILURE;
        }

        $json = json_decode((string) file_get_contents($configPath), true);
        if (! is_array($json) || empty($json['template']) || empty($json['endpoint'])) {
            $this->error('Config must have "template" and "endpoint" keys.');

            return self::FAILURE;
        }

        $templateDef = $json['template'];
        $endpointDef = $json['endpoint'];

        $templateName = $templateDef['name'] ?? null;
        $endpointName = $endpointDef['name'] ?? null;

        if (! $templateName || ! $endpointName) {
            $this->error('Template and endpoint must have "name".');

            return self::FAILURE;
        }

        // 1. Find or create template
        $template = $client->getTemplateByName($templateName);
        if ($template && $this->option('force')) {
            $this->warn("Template '{$templateName}' exists. Use RunPod dashboard to delete before --force recreate.");
            $templateId = $template['id'];
        } elseif ($template) {
            $this->info("Template '{$templateName}' exists (id: {$template['id']}).");
            $templateId = $template['id'];
        } else {
            $templateInput = $this->mapTemplateInput($templateDef);
            $created = $client->createTemplate($templateInput);
            if (! $created || empty($created['id'])) {
                $this->error('Failed to create template: '.($client->getLastError() ?: 'unknown'));

                return self::FAILURE;
            }
            $templateId = $created['id'];
            $this->info("Created template '{$templateName}' (id: {$templateId}).");
        }

        // 2. Find or create endpoint
        $existing = $podClient->getServerlessEndpointByName($endpointName);
        if ($existing && $this->option('force')) {
            $this->warn("Endpoint '{$endpointName}' exists. Use RunPod dashboard to delete before --force recreate.");
            $endpointId = $existing['endpoint_id'];
        } elseif ($existing) {
            $this->info("Endpoint '{$endpointName}' exists (id: {$existing['endpoint_id']}).");
            $endpointId = $existing['endpoint_id'];
        } else {
            $endpointInput = $this->mapEndpointInput($endpointDef, $templateId);
            $created = $client->createEndpoint($endpointInput);
            if (! $created || empty($created['id'])) {
                $this->error('Failed to create endpoint: '.($client->getLastError() ?: 'unknown'));

                return self::FAILURE;
            }
            $endpointId = $created['id'];
            $this->info("Created endpoint '{$endpointName}' (id: {$endpointId}).");
        }

        $this->newLine();
        $this->line('Add to .env:');
        $this->line("<info>RUNPOD_GRANITE_ENDPOINT_ID={$endpointId}</info>");

        return self::SUCCESS;
    }

    protected function resolveConfigPath(): ?string
    {
        if ($path = $this->option('config')) {
            return str_starts_with($path, '/') ? $path : base_path($path);
        }

        if ($instance = $this->option('instance')) {
            $config = config("runpod.instances.{$instance}", []);
            $path = $config['serverless_config_path'] ?? null;
            if ($path) {
                return str_starts_with($path, '/') ? $path : base_path($path);
            }
        }

        return null;
    }

    /**
     * Map config JSON "template" to RunPod createTemplate input.
     */
    protected function mapTemplateInput(array $def): array
    {
        $input = [
            'name' => $def['name'] ?? 'unnamed',
            'imageName' => $def['imageName'] ?? 'runpod/base:0.4.0',
            'isServerless' => $def['isServerless'] ?? true,
        ];

        if (isset($def['containerDiskInGb'])) {
            $input['containerDiskInGb'] = (int) $def['containerDiskInGb'];
        }
        if (! empty($def['env']) && is_array($def['env'])) {
            $input['env'] = $def['env'];
        }
        if (isset($def['readme'])) {
            $input['readme'] = (string) $def['readme'];
        }

        return $input;
    }

    /**
     * Map config JSON "endpoint" to RunPod createEndpoint input.
     */
    protected function mapEndpointInput(array $def, string $templateId): array
    {
        $input = [
            'templateId' => $templateId,
            'name' => $def['name'] ?? 'unnamed',
            'computeType' => $def['computeType'] ?? 'GPU',
        ];

        if (isset($def['gpuTypeIds']) && is_array($def['gpuTypeIds'])) {
            $input['gpuTypeIds'] = $def['gpuTypeIds'];
        }
        if (isset($def['gpuCount'])) {
            $input['gpuCount'] = (int) $def['gpuCount'];
        }
        if (isset($def['workersMin'])) {
            $input['workersMin'] = (int) $def['workersMin'];
        }
        if (isset($def['workersMax'])) {
            $input['workersMax'] = (int) $def['workersMax'];
        }

        return $input;
    }
}
