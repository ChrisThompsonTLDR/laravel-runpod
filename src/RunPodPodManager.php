<?php

namespace ChrisThompsonTLDR\LaravelRunPod;

class RunPodPodManager
{
    protected ?string $nickname = null;

    public function __construct(
        protected RunPodPodClient $client,
        protected string $stateFilePath,
        protected array $podConfig
    ) {}

    public function setNickname(?string $nickname): void
    {
        $this->nickname = $nickname;
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
            $pod = $this->client->getPod($state['pod_id']);
            if ($pod && ($pod['desiredStatus'] ?? '') === 'RUNNING') {
                return [
                    'pod_id' => $state['pod_id'],
                    'url' => $this->client->getPublicUrl($state['pod_id']),
                ];
            }
        }

        $created = $this->createPod();

        if (! $created) {
            return null;
        }

        $this->writeState([
            'pod_id' => $created['id'],
            'last_run_at' => now()->toIso8601String(),
        ]);

        $url = $this->waitForPodUrl($created['id']);

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

        return $this->client->getPod($state['pod_id']);
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

        if (! empty($config['data_center_ids'])) {
            $input['dataCenterIds'] = (array) $config['data_center_ids'];
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
            $pod = $this->client->getPod($podId);
            if ($pod && ($pod['desiredStatus'] ?? '') === 'RUNNING') {
                $url = $this->client->getPublicUrl($podId);
                if ($url) {
                    return $url;
                }
            }
        }

        return null;
    }

    protected function resolveStatePath(): ?string
    {
        $path = $this->stateFilePath;

        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:#', $path)) {
            return $path;
        }

        return storage_path('app/'.ltrim($path, '/'));
    }
}
