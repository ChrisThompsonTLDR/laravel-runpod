<?php

namespace ChrisThompsonTLDR\LaravelRunPod;

use ChrisThompsonTLDR\LaravelRunPod\Exceptions\RunPodApiKeyNotConfiguredException;
use Illuminate\Support\Facades\Http;

class RunPodGraphQLClient
{
    protected string $endpoint = 'https://api.runpod.io/graphql';

    private const POD_TELEMETRY_QUERY = <<<'GRAPHQL'
query pod($input: PodFilter) {
  pod(input: $input) {
    id
    name
    desiredStatus
    costPerHr
    latestTelemetry {
      time
      state
      cpuUtilization
      memoryUtilization
      averageGpuMetrics {
        percentUtilization
        memoryUtilization
        temperatureCelcius
        powerWatts
      }
      individualGpuMetrics {
        id
        percentUtilization
        memoryUtilization
        temperatureCelcius
        powerWatts
      }
    }
  }
}
GRAPHQL;

    public function __construct(
        protected string $apiKey
    ) {}

    /**
     * Pod telemetry (CPU, GPU, memory). Returns null if not found or not running.
     */
    public function getPodTelemetry(string $podId): ?array
    {
        if ($this->apiKey === '') {
            throw new RunPodApiKeyNotConfiguredException;
        }

        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->post($this->endpoint, [
                'query' => self::POD_TELEMETRY_QUERY,
                'variables' => ['input' => ['podId' => $podId]],
            ]);

        if (! $response->successful()) {
            return null;
        }

        $pod = $response->json('data.pod');
        $telemetry = is_array($pod) ? ($pod['latestTelemetry'] ?? null) : null;

        return is_array($telemetry) ? $telemetry : null;
    }
}
