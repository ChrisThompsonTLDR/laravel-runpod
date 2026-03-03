<?php

namespace ChrisThompsonTLDR\LaravelRunPod;

/**
 * Pod lifecycle client. Delegates all HTTP operations to RunPodClient.
 */
class RunPodPodClient
{
    public function __construct(
        protected RunPodClient $client,
        protected ?RunPodGraphQLClient $graphql = null
    ) {}

    public function createPod(array $input): ?array
    {
        return $this->client->createPod($input);
    }

    /**
     * Get a pod by ID. Pass params for includeMachine, includeNetworkVolume, etc.
     */
    public function getPod(string $podId, array $params = []): ?array
    {
        return $this->client->getPod($podId, $params);
    }

    /**
     * Get pod telemetry (CPU, GPU, memory utilization) via GraphQL.
     */
    public function getPodTelemetry(string $podId): ?array
    {
        if (! $this->graphql) {
            return null;
        }

        return $this->graphql->getPodTelemetry($podId);
    }

    public function stopPod(string $podId): bool
    {
        return $this->client->stopPod($podId) !== null;
    }

    public function terminatePod(string $podId): bool
    {
        return $this->client->deletePod($podId);
    }

    /**
     * Return a summary of the account's current resource usage.
     * Uses the REST list endpoints to aggregate pods, serverless endpoints,
     * and network volumes.
     */
    public function getMyself(): ?array
    {
        $pods = $this->client->listPods();
        $endpoints = $this->client->listEndpoints();
        $networkVolumes = $this->client->listNetworkVolumes();

        return [
            'pods' => $pods,
            'endpoints' => $endpoints,
            'networkVolumes' => $networkVolumes,
        ];
    }

    /**
     * Build the public proxy URL for an HTTP port on a pod.
     * Parses the REST API's ports array (e.g. ["8000/http", "22/tcp"]).
     * Checks both top-level 'ports' and 'runtime.ports' for API compatibility.
     */
    public function getPublicUrl(string $podId, int $privatePort = 8000): ?string
    {
        $pod = $this->getPod($podId);
        if (! $pod) {
            return sprintf('https://%s-%d.proxy.runpod.net', $podId, $privatePort);
        }

        $ports = $pod['ports'] ?? $pod['runtime']['ports'] ?? [];

        foreach ($ports as $portSpec) {
            if (is_string($portSpec) && str_contains($portSpec, '/') && str_ends_with($portSpec, '/http')) {
                $portNum = (int) explode('/', $portSpec)[0];

                return sprintf('https://%s-%d.proxy.runpod.net', $podId, $portNum ?: $privatePort);
            }
        }

        return sprintf('https://%s-%d.proxy.runpod.net', $podId, $privatePort);
    }
}
