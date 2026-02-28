<?php

namespace ChrisThompsonTLDR\LaravelRunPod;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Unified control plane for RunPod Pods and Serverless.
 *
 * Fluent, Laravel-esque API:
 *
 *   RunPod::for(PymupdfJob::class)
 *       ->disk('runpod')
 *       ->ensure($filename);
 *
 *   $pod = RunPod::for(PymupdfJob::class)
 *       ->instance('pymupdf')
 *       ->start();
 *
 *   // Serverless with idleTimeout, or Pod with scheduler prune
 *   RunPod::instance('pymupdf')->startWithPrune('everyFiveMinutes');
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
    public function disk(string $disk): RunPodFileManager
    {
        $instanceConfig = $this->instanceName ? $this->getInstanceConfig() : [];
        $loadPath = $instanceConfig['load_path'] ?? config('runpod.load_path', storage_path('app/runpod'));
        $remotePrefix = $instanceConfig['remote_prefix'] ?? config('runpod.remote_prefix', 'data');

        return new RunPodFileManager(
            Storage::disk($disk),
            $loadPath,
            $remotePrefix
        );
    }

    /**
     * Select an instance by nickname (configured in config/runpod.php).
     */
    public function instance(string $name): static
    {
        $this->instanceName = $name;

        return $this;
    }

    /**
     * Ensure pod is running and return pod info (url, pod_id).
     */
    public function start(): ?array
    {
        $config = $this->getInstanceConfig();
        $podConfig = array_merge(config('runpod.pod', []), $config['pod'] ?? []);
        $this->podManager->configure($podConfig);
        $this->podManager->setStatePath($config['state_file'] ?? $this->resolveStatePath());

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

        $base = config('runpod.state_file', storage_path('app/runpod-pod-state.json'));
        $nickname = $this->instanceName ?? $this->nickname ?? 'default';
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nickname);

        if (str_ends_with($base, '.json')) {
            return preg_replace('/\.json$/', "-{$safe}.json", $base);
        }

        return $base.'.'.$safe;
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
