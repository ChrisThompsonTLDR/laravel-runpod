<?php

namespace ChrisThompsonTLDR\LaravelRunPod;

use ChrisThompsonTLDR\LaravelRunPod\Events\GuardrailsTripped;
use ChrisThompsonTLDR\LaravelRunPod\Exceptions\GuardrailsExceededException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

class RunPodGuardrails
{
    protected const CACHE_KEY = 'runpod_guardrails_usage';

    public function __construct(
        protected RunPodClient $client
    ) {}

    /**
     * Ensure current usage is within guardrail limits. Throws if exceeded.
     *
     * @throws GuardrailsExceededException
     */
    public function check(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $usage = $this->getUsage();
        $limits = $this->getLimits();

        $this->checkPods($usage, $limits);
        $this->checkServerless($usage, $limits);
        $this->checkStorage($usage, $limits);
    }

    /**
     * Check before creating a pod. Throws if creating would exceed limits.
     *
     * @throws GuardrailsExceededException
     */
    public function checkBeforeCreatePod(): void
    {
        $this->check();

        $limits = $this->getLimits();
        $podLimits = $limits['pods'] ?? [];

        if (isset($podLimits['pods_max'])) {
            $usage = $this->getUsage();
            $current = count($usage['pods'] ?? []);
            $limit = (int) $podLimits['pods_max'];
            if ($limit > 0 && $current >= $limit) {
                $this->tripAndThrow('pods', 'pods_max', $current, $limit);
            }
        }

        if (isset($podLimits['pods_running_max'])) {
            $usage = $this->getUsage();
            $running = $this->countRunningPods($usage['pods'] ?? []);
            $limit = (int) $podLimits['pods_running_max'];
            if ($limit > 0 && $running >= $limit) {
                $this->tripAndThrow('pods', 'pods_running_max', $running, $limit);
            }
        }
    }

    public function getUsage(): array
    {
        $cacheTtl = $this->getCacheTtlSeconds();

        return Cache::remember(self::CACHE_KEY, $cacheTtl, function () {
            $pods = $this->client->listPods();
            $endpoints = $this->client->listEndpoints();
            $networkVolumes = $this->client->listNetworkVolumes();

            $workersTotal = 0;
            foreach ($endpoints as $ep) {
                $workersTotal += (int) ($ep['workersMax'] ?? 0);
            }

            return [
                'pods' => $pods,
                'pods_count' => count($pods),
                'pods_running_count' => $this->countRunningPods($pods),
                'endpoints' => $endpoints,
                'endpoints_count' => count($endpoints),
                'workers_total' => $workersTotal,
                'network_volumes' => $networkVolumes,
                'network_volumes_count' => count($networkVolumes),
                'storage_total_gb' => $this->sumVolumeGb($networkVolumes),
            ];
        });
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected function isEnabled(): bool
    {
        return config('runpod.guardrails.enabled', true);
    }

    protected function getLimits(): array
    {
        return config('runpod.guardrails.limits', []);
    }

    protected function getCacheTtlSeconds(): int
    {
        $schedule = config('runpod.guardrails.cache_schedule', 'everyFifteenMinutes');
        $map = [
            'everyMinute' => 60,
            'everyTwoMinutes' => 120,
            'everyThreeMinutes' => 180,
            'everyFourMinutes' => 240,
            'everyFiveMinutes' => 300,
            'everyTenMinutes' => 600,
            'everyFifteenMinutes' => 900,
            'everyThirtyMinutes' => 1800,
            'hourly' => 3600,
        ];

        return $map[$schedule] ?? 900;
    }

    protected function checkPods(array $usage, array $limits): void
    {
        $podLimits = $limits['pods'] ?? [];
        if (empty($podLimits)) {
            return;
        }

        $count = $usage['pods_count'] ?? 0;
        $running = $usage['pods_running_count'] ?? 0;

        if (isset($podLimits['pods_max']) && ($limit = (int) $podLimits['pods_max']) > 0 && $count >= $limit) {
            $this->tripAndThrow('pods', 'pods_max', $count, $limit);
        }

        if (isset($podLimits['pods_running_max']) && ($limit = (int) $podLimits['pods_running_max']) > 0 && $running >= $limit) {
            $this->tripAndThrow('pods', 'pods_running_max', $running, $limit);
        }
    }

    protected function checkServerless(array $usage, array $limits): void
    {
        $serverlessLimits = $limits['serverless'] ?? [];
        if (empty($serverlessLimits)) {
            return;
        }

        $endpointsCount = $usage['endpoints_count'] ?? 0;
        $workersTotal = $usage['workers_total'] ?? 0;

        if (isset($serverlessLimits['endpoints_max']) && ($limit = (int) $serverlessLimits['endpoints_max']) > 0 && $endpointsCount >= $limit) {
            $this->tripAndThrow('serverless', 'endpoints_max', $endpointsCount, $limit);
        }

        if (isset($serverlessLimits['workers_total_max']) && ($limit = (int) $serverlessLimits['workers_total_max']) > 0 && $workersTotal >= $limit) {
            $this->tripAndThrow('serverless', 'workers_total_max', $workersTotal, $limit);
        }
    }

    protected function checkStorage(array $usage, array $limits): void
    {
        $storageLimits = $limits['storage'] ?? [];
        if (empty($storageLimits)) {
            return;
        }

        $volumesCount = $usage['network_volumes_count'] ?? 0;
        $totalGb = $usage['storage_total_gb'] ?? 0;

        if (isset($storageLimits['network_volumes_max']) && ($limit = (int) $storageLimits['network_volumes_max']) > 0 && $volumesCount >= $limit) {
            $this->tripAndThrow('storage', 'network_volumes_max', $volumesCount, $limit);
        }

        if (isset($storageLimits['volume_size_gb_max']) && ($limit = (int) $storageLimits['volume_size_gb_max']) > 0 && $totalGb >= $limit) {
            $this->tripAndThrow('storage', 'volume_size_gb_max', (int) $totalGb, $limit);
        }
    }

    protected function countRunningPods(array $pods): int
    {
        $count = 0;
        foreach ($pods as $pod) {
            if (($pod['desiredStatus'] ?? '') === 'RUNNING') {
                $count++;
            }
        }

        return $count;
    }

    protected function sumVolumeGb(array $volumes): float
    {
        $sum = 0;
        foreach ($volumes as $vol) {
            // REST API uses 'size'; fall back to 'volumeInGb' for compatibility
            $sum += (float) ($vol['size'] ?? $vol['volumeInGb'] ?? 0);
        }

        return $sum;
    }

    protected function tripAndThrow(string $service, string $limit, int $current, int $limitValue): never
    {
        Event::dispatch(new GuardrailsTripped($service, $limit, $current, $limitValue));

        $exception = match ($service) {
            'pods' => $limit === 'pods_running_max'
                ? GuardrailsExceededException::podsRunning($current, $limitValue)
                : GuardrailsExceededException::pods($current, $limitValue),
            'serverless' => $limit === 'workers_total_max'
                ? GuardrailsExceededException::serverlessWorkers($current, $limitValue)
                : GuardrailsExceededException::serverlessEndpoints($current, $limitValue),
            'storage' => $limit === 'volume_size_gb_max'
                ? GuardrailsExceededException::storageSizeGb($current, $limitValue)
                : GuardrailsExceededException::storageVolumes($current, $limitValue),
            default => new GuardrailsExceededException($service, $limit, $current, $limitValue),
        };

        throw $exception;
    }
}
