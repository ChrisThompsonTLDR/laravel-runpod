<?php

namespace ChrisThompsonTLDR\LaravelRunPod;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Control plane for RunPod pods and serverless endpoints.
 *
 *   RunPod::for(ExampleJob::class)->disk()->ensure($filename);
 *   $pod = RunPod::for(ExampleJob::class)->instance('example')->start();
 *   RunPod::instance('example')->startWithPrune('everyFiveMinutes');
 */
class RunPod
{
    protected ?string $nickname = null;

    protected ?string $instanceName = null;

    protected ?array $podResult = null;

    public function __construct(
        protected RunPodPodManager $podManager,
        protected RunPodPodClient $client
    ) {}

    /**
     * Set the "nickname" (cache key) for this pod's state.
     * Used for per-consumer last_run_at tracking and prune scheduling.
     */
    public function for(string $nickname): static
    {
        $this->nickname = $nickname;
        $this->podManager->setNickname($nickname);
        $this->podManager->setStatePath($this->resolveStatePath());
        $this->podManager->updateLastRunAt();

        return $this;
    }

    /**
     * Get a file manager for the given disk, with Laravel filesystem methods.
     */
    public function disk(?string $disk = null): RunPodFileManager
    {
        $instanceConfig = $this->instanceName ? $this->getInstanceConfig() : [];
        $isLocal = ($instanceConfig['type'] ?? 'pod') === 'local';

        if ($disk === null) {
            $disk = $isLocal
                ? ($instanceConfig['local_disk'] ?? 'local')
                : (($instanceConfig['remote_disk'] ?? [])['disk_name'] ?? config('runpod.disk', 'runpod'));
        }

        $loadPath = $instanceConfig['load_path'] ?? storage_path('app/runpod');
        $remotePrefix = ($instanceConfig['remote_disk'] ?? [])['prefix'] ?? 'data';

        return new RunPodFileManager(
            Storage::disk($disk),
            $loadPath,
            $remotePrefix,
            $isLocal
        );
    }

    /**
     * Select an instance by name (configured in config/runpod.php).
     */
    public function instance(string $name): static
    {
        $this->instanceName = $name;

        return $this;
    }

    /**
     * Ensure pod or serverless endpoint is ready and return info (url, pod_id or endpoint_id).
     * For serverless: looks up endpoint by name (from config or serverless_config JSON).
     */
    public function start(): ?array
    {
        $config = $this->getInstanceConfig();
        $type = $config['type'] ?? 'pod';

        if ($type === 'serverless') {
            return $this->ensureServerlessEndpoint($config);
        }

        $podConfig = $config;
        $podConfig['local'] = ($config['type'] ?? 'pod') === 'local';
        if (isset($config['local_url'])) {
            $podConfig['local_url'] = $config['local_url'];
        }
        $this->podManager->configure($podConfig);
        $this->podManager->setStatePath($config['state_file'] ?? $this->resolveStatePath());
        $this->podManager->setInstanceName($this->instanceName);

        if ($this->nickname) {
            $this->podManager->updateLastRunAt();
        }

        $pod = $this->podManager->ensurePod();
        if (! $pod || ! ($pod['url'] ?? null)) {
            return null;
        }

        $this->podResult = $pod;

        if ($this->nickname) {
            $this->podManager->updateLastRunAt();
        }

        return $pod;
    }

    /**
     * Resolve serverless endpoint: read from state file first, then API lookup by name.
     */
    protected function ensureServerlessEndpoint(array $config): ?array
    {
        $instance = $this->instanceName ?? 'default';
        $endpointState = app(RunPodEndpointState::class);
        $state = $endpointState->read($instance);
        if ($state && ! empty($state['endpoint_id']) && ! empty($state['url'])) {
            $this->podResult = [
                'url' => $state['url'],
                'endpoint_id' => $state['endpoint_id'],
            ];
            if ($this->nickname) {
                $this->podManager->updateLastRunAt();
            }

            return $this->podResult;
        }

        $endpointName = $config['serverless']['endpoint_name'] ?? null;
        if (! $endpointName && ! empty($config['serverless_config_path'] ?? null)) {
            $path = $config['serverless_config_path'];
            $path = str_starts_with($path, '/') ? $path : base_path($path);
            if (is_file($path)) {
                $json = json_decode((string) file_get_contents($path), true);
                $endpointName = $json['endpoint']['name'] ?? null;
            }
        }

        if (! $endpointName) {
            return null;
        }

        $result = $this->client->getServerlessEndpointByName($endpointName);
        if (! $result) {
            return null;
        }

        $this->podResult = [
            'url' => $result['url'],
            'endpoint_id' => $result['endpoint_id'],
        ];

        if ($this->nickname) {
            $this->podManager->updateLastRunAt();
        }

        return $this->podResult;
    }

    /**
     * Get full pod details from the RunPod API (includes networkVolumeId, desiredStatus, etc.).
     * Does not update last_run_at (unlike for()->pod()).
     */
    public function pod(): ?array
    {
        if ($this->instanceName) {
            $config = $this->getInstanceConfig();
            $podConfig = $config;
            $podConfig['local'] = ($config['type'] ?? 'pod') === 'local';
            if (isset($config['local_url'])) {
                $podConfig['local_url'] = $config['local_url'];
            }
            $this->podManager->configure($podConfig);
            $this->podManager->setStatePath($config['state_file'] ?? $this->resolveStatePath());
            $this->podManager->setInstanceName($this->instanceName);
        }

        return $this->podManager->getPodDetails();
    }

    /**
     * Get the pod URL after start(). Returns null if not started.
     */
    public function url(): ?string
    {
        return $this->podResult['url'] ?? $this->podManager->getPodUrl();
    }

    /**
     * Configure prune behavior.
     * - For Pods: prune_schedule is read from instance config; scheduler runs runpod:prune per instance.
     * - For Serverless: uses idleTimeout (minutes) - built-in, no scheduler needed.
     *
     * @param  string  $scheduleMethod  Laravel schedule method (e.g. 'everyFiveMinutes') or minutes for serverless idleTimeout
     */
    public function startWithPrune(string $scheduleMethod = 'everyFiveMinutes'): static
    {
        $config = $this->getInstanceConfig();
        $type = $config['type'] ?? 'pod';

        if ($type === 'serverless') {
            // Serverless: idleTimeout is built-in when creating endpoint
            $config['serverless']['idle_timeout'] = $this->parsePruneToMinutes($scheduleMethod);
        }
        // Pod: prune_schedule comes from config/runpod.php instances[].prune_schedule

        return $this;
    }

    /**
     * Get instance config with local flag and local_url merged.
     */
    public static function mergedPodConfigForInstance(string $instance): array
    {
        $config = config("runpod.instances.{$instance}", []);
        $merged = $config;
        $merged['local'] = ($config['type'] ?? 'pod') === 'local';
        if (isset($config['local_url'])) {
            $merged['local_url'] = $config['local_url'];
        }

        return $merged;
    }

    protected function getInstanceConfig(): array
    {
        $instances = config('runpod.instances', []);

        if ($this->instanceName && isset($instances[$this->instanceName])) {
            return $instances[$this->instanceName];
        }

        return [];
    }

    protected function resolveStatePath(): string
    {
        $config = $this->getInstanceConfig();
        if (! empty($config['state_file'])) {
            return $config['state_file'];
        }

        $nickname = $this->instanceName ?? $this->nickname ?? 'default';
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nickname);

        return storage_path("app/runpod-pod-state-{$safe}.json");
    }

    protected function parsePruneToMinutes(string $scheduleMethod): int
    {
        $map = [
            'everyMinute' => 1,
            'everyTwoMinutes' => 2,
            'everyThreeMinutes' => 3,
            'everyFourMinutes' => 4,
            'everyFiveMinutes' => 5,
            'everyTenMinutes' => 10,
            'everyFifteenMinutes' => 15,
            'everyThirtyMinutes' => 30,
            'hourly' => 60,
        ];

        return $map[$scheduleMethod] ?? 5;
    }
}
