<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Exceptions;

use Exception;

class GuardrailsExceededException extends Exception
{
    public function __construct(
        string $service,
        string $limit,
        int $current,
        int $limitValue,
        ?\Throwable $previous = null
    ) {
        $message = sprintf(
            'RunPod API guardrail exceeded: %s limit "%s" is %d (current usage: %d). Reduce usage or increase the limit in config/runpod.php guardrails.',
            $service,
            $limit,
            $limitValue,
            $current
        );

        parent::__construct($message, 0, $previous);
    }

    public static function pods(int $current, int $limit): self
    {
        return new self('pods', 'pods_max', $current, $limit);
    }

    public static function podsRunning(int $current, int $limit): self
    {
        return new self('pods', 'pods_running_max', $current, $limit);
    }

    public static function serverlessEndpoints(int $current, int $limit): self
    {
        return new self('serverless', 'endpoints_max', $current, $limit);
    }

    public static function serverlessWorkers(int $current, int $limit): self
    {
        return new self('serverless', 'workers_total_max', $current, $limit);
    }

    public static function storageVolumes(int $current, int $limit): self
    {
        return new self('storage', 'network_volumes_max', $current, $limit);
    }

    public static function storageSizeGb(int $current, int $limit): self
    {
        return new self('storage', 'volume_size_gb_max', $current, $limit);
    }

    public static function apiRequestsPerMinute(int $current, int $limit): self
    {
        return new self('api', 'requests_per_minute', $current, $limit);
    }
}
