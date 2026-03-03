<?php

namespace ChrisThompsonTLDR\LaravelRunPod;

use Illuminate\Support\Facades\Http;

class RunPodGraphQLClient
{
    protected string $endpoint = 'https://api.runpod.io/graphql';

    public function __construct(
        protected string $apiKey
    ) {}

    /**
     * Fetch pod telemetry (CPU, GPU, memory utilization) via GraphQL.
     * Returns latestTelemetry data or null if pod not found or not running.
     */
    public function getPodTelemetry(string $podId): ?array
    {
        $query = <<<'GRAPHQL'
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

        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->contentType('application/json')
            ->post($this->endpoint, [
                'query' => $query,
                'variables' => [
                    'input' => ['podId' => $podId],
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $pod = $data['data']['pod'] ?? null;

        if (! $pod) {
            return null;
        }

        $telemetry = $pod['latestTelemetry'] ?? null;

        return is_array($telemetry) ? $telemetry : null;
    }
}
