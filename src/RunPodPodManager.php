<?php

namespace ChrisThompsonTLDR\LaravelRunPod;

class RunPodPodManager
{
    protected ?string $nickname = null;

    protected ?string $instanceName = null;

    public function __construct(
        protected RunPodPodClient $client,
        protected string $stateFilePath,
        protected array $podConfig,
        protected ?RunPodStatsWriter $statsWriter = null
    ) {}

    public function setNickname(?string $nickname): void
    {
        $this->nickname = $nickname;
    }

    public function setInstanceName(?string $instanceName): void
    {
        $this->instanceName = $instanceName;
    }

    public function setStatePath(string $path): void
    {
        $this->stateFilePath = $path;
    }

    public function configure(array $podConfig): void
    {
        $base = config('runpod.pod', []);
        $this->podConfig = array_merge($base, $podConfig);
    }

    public function ensurePod(): ?array
    {
        $state = $this->readState();

        if ($state && ($state['pod_id'] ?? null)) {
            $pod = $this->client->getPod($state['pod_id'], $this->podParamsForStats());
            if ($pod && ($pod['desiredStatus'] ?? '') === 'RUNNING') {
                $result = [
                    'pod_id' => $state['pod_id'],
                    'url' => $this->client->getPublicUrl($state['pod_id']),
                ];
                $this->writeStats($pod, $state['last_run_at'] ?? null);

                return $result;
            }
        }

        $created = $this->createPod();

        if (! $created) {
            return null;
        }

        $lastRunAt = now()->toIso8601String();
        $this->writeState([
            'pod_id' => $created['id'],
            'last_run_at' => $lastRunAt,
        ]);

        $url = $this->waitForPodUrl($created['id']);

        $pod = $this->client->getPod($created['id'], $this->podParamsForStats());
        if ($pod) {
            $this->writeStats($pod, $lastRunAt);
        }

        return [
            'pod_id' => $created['id'],
            'url' => $url ?? $this->client->getPublicUrl($created['id']),
        ];
    }

    public function updateLastRunAt(): void
    {
        $state = $this->readState();

        if ($state && ($state['pod_id'] ?? null)) {
            $this->writeState(array_merge($state, [
                'last_run_at' => now()->toIso8601String(),
            ]));
        }
    }

    public function terminatePod(): bool
    {
        $state = $this->readState();

        if (! $state || ! ($state['pod_id'] ?? null)) {
            $this->clearState();

            return true;
        }

        $ok = $this->client->terminatePod($state['pod_id']);
        $this->clearState();

        return $ok;
    }

    public function pruneIfInactive(): bool
    {
        $state = $this->readState();

        if (! $state || ! ($state['pod_id'] ?? null) || ! ($state['last_run_at'] ?? null)) {
            return false;
        }

        $lastRun = \Carbon\Carbon::parse($state['last_run_at']);
        $idleMinutes = config('runpod.pod.inactivity_minutes', 2);

        if ($lastRun->diffInMinutes(now(), false) < $idleMinutes) {
            return false;
        }

        return $this->terminatePod();
    }

    /**
     * Get full pod details from the RunPod API (includes networkVolumeId, etc.).
     */
    public function getPodDetails(): ?array
    {
        $state = $this->readState();

        if (! $state || ! ($state['pod_id'] ?? null)) {
            return null;
        }

        $pod = $this->client->getPod($state['pod_id'], $this->podParamsForStats());
        if ($pod) {
            $this->writeStats($pod, $state['last_run_at'] ?? null);
        }

        return $pod;
    }

    public function getPodUrl(): ?string
    {
        $state = $this->readState();

        if (! $state || ! ($state['pod_id'] ?? null)) {
            return null;
        }

        return $this->client->getPublicUrl($state['pod_id']);
    }

    protected function createPod(): ?array
    {
        if (config('runpod.guardrails.enabled', true)) {
            app(RunPodGuardrails::class)->checkBeforeCreatePod();
        }

        $config = $this->podConfig;

        if (empty($config['image_name'])) {
            return null;
        }

        $gpuCount = (int) ($config['gpu_count'] ?? 0);

        // Convert env from [{key: K, value: V}] (config format) to {K: V} (REST format)
        $env = [];
        foreach ($config['env'] ?? [] as $item) {
            if (isset($item['key'], $item['value'])) {
                $env[$item['key']] = $item['value'];
            }
        }

        // Convert ports from string "8000/http" or "8000/http,22/tcp" to array
        $ports = $config['ports'] ?? '8000/http';
        if (is_string($ports)) {
            $ports = array_values(array_filter(array_map('trim', explode(',', $ports))));
        }

        $containerDiskInGb = $config['container_disk_in_gb'] ?? 50;
        if ($gpuCount === 0) {
            $containerDiskInGb = min($containerDiskInGb, $config['cpu_container_disk_max_gb'] ?? 20);
        }

        $input = [
            'name' => $config['name'] ?? 'runpod-pod',
            'imageName' => $config['image_name'],
            'volumeInGb' => $config['volume_in_gb'] ?? 50,
            'containerDiskInGb' => $containerDiskInGb,
            'volumeMountPath' => $config['volume_mount_path'] ?? '/workspace',
            'ports' => $ports,
            'env' => $env,
        ];

        $vcpuCount = $config['min_vcpu_count'] ?? $config['vcpu_count'] ?? 2;
        $minMemoryInGb = $config['min_memory_in_gb'] ?? 15;

        if ($gpuCount > 0) {
            $input['gpuCount'] = $gpuCount;
            $gpuTypeId = $config['gpu_type_id'] ?? 'NVIDIA GeForce RTX 4090';
            $input['gpuTypeIds'] = [$gpuTypeId];
            $input['minRAMPerGPU'] = $minMemoryInGb;
            $input['minVCPUPerGPU'] = $vcpuCount;
        } else {
            $input['computeType'] = 'CPU';
            $input['vcpuCount'] = $vcpuCount;
            if (! empty($config['cpu_flavor_ids'])) {
                $input['cpuFlavorIds'] = (array) $config['cpu_flavor_ids'];
            }
        }

        if (! empty($config['network_volume_id'])) {
            $input['networkVolumeId'] = $config['network_volume_id'];
        }

        $dataCenterIds = $this->filterValidDataCenterIds($config['data_center_ids'] ?? []);
        if (! empty($dataCenterIds)) {
            $input['dataCenterIds'] = $dataCenterIds;
        }

        return $this->client->createPod($input);
    }

    protected function readState(): ?array
    {
        $path = $this->resolveStatePath();

        if (! $path || ! file_exists($path)) {
            return null;
        }

        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    protected function writeState(array $state): void
    {
        $path = $this->resolveStatePath();

        if (! $path) {
            $path = storage_path('app/runpod-pod-state.json');
        }

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT));
    }

    protected function clearState(): void
    {
        $path = $this->resolveStatePath();

        if ($path && file_exists($path)) {
            unlink($path);
        }
    }

    protected function waitForPodUrl(string $podId, int $maxAttempts = 30): ?string
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep(5);
            $pod = $this->client->getPod($podId, []);
            if ($pod && ($pod['desiredStatus'] ?? '') === 'RUNNING') {
                $url = $this->client->getPublicUrl($podId);
                if ($url) {
                    return $url;
                }
            }
        }

        return null;
    }

    /**
     * RunPod API only accepts specific data center IDs. Invalid values (e.g. deprecated US-MD-1)
     * cause schema validation errors. Filter to valid IDs only; omit if none remain.
     *
     * @see https://rest.runpod.io/v1/pods schema
     */
    protected function filterValidDataCenterIds(array $ids): array
    {
        $valid = [
            'AP-JP-1', 'CA-MTL-1', 'CA-MTL-2', 'CA-MTL-3', 'EU-CZ-1', 'EU-FR-1', 'EU-NL-1',
            'EU-RO-1', 'EU-SE-1', 'EUR-IS-1', 'EUR-IS-2', 'EUR-IS-3', 'EUR-NO-1', 'OC-AU-1',
            'US-CA-2', 'US-DE-1', 'US-GA-1', 'US-GA-2', 'US-IL-1', 'US-KS-2', 'US-KS-3',
            'US-NC-1', 'US-TX-1', 'US-TX-3', 'US-TX-4', 'US-WA-1',
        ];

        $filtered = array_values(array_intersect((array) $ids, $valid));

        return $filtered;
    }

    protected function resolveStatePath(): ?string
    {
        $path = $this->stateFilePath;

        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:#', $path)) {
            return $path;
        }

        return storage_path('app/'.ltrim($path, '/'));
    }

    protected function podParamsForStats(): array
    {
        return [
            'includeMachine' => true,
            'includeNetworkVolume' => true,
        ];
    }

    protected function writeStats(array $pod, ?string $lastRunAt): void
    {
        if (! $this->statsWriter || ! $this->instanceName) {
            return;
        }

        $podId = $pod['id'] ?? null;
        if (! $podId) {
            return;
        }

        $telemetry = $this->client->getPodTelemetry($podId);
        $this->statsWriter->write($this->instanceName, $pod, $telemetry, $lastRunAt);
    }
}
