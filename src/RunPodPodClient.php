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
     * Build the public URL for a port on a pod.
     * For TCP ports: uses direct URL (publicIp + portMappings) since the proxy does not support TCP.
     * For HTTP ports: uses RunPod proxy (https://{podId}-{port}.proxy.runpod.net).
     */
    public function getPublicUrl(string $podId, int $privatePort = 8000): ?string
    {
        $pod = $this->getPod($podId);
        if (! $pod) {
            return sprintf('https://%s-%d.proxy.runpod.net', $podId, $privatePort);
        }

        $ports = $pod['ports'] ?? $pod['runtime']['ports'] ?? [];
        $publicIp = $pod['publicIp'] ?? null;
        $portMappings = $pod['portMappings'] ?? [];

        foreach ($ports as $portSpec) {
            if (! is_string($portSpec) || ! str_contains($portSpec, '/')) {
                continue;
            }
            [$portNum, $protocol] = explode('/', $portSpec, 2);
            $portNum = (int) $portNum ?: $privatePort;

            if (($protocol ?? '') === 'http' || str_ends_with($protocol ?? '', '/http')) {
                return sprintf('https://%s-%d.proxy.runpod.net', $podId, $portNum);
            }

            if (($protocol === 'tcp' || str_ends_with($protocol ?? '', '/tcp')) && $publicIp && isset($portMappings[(string) $portNum])) {
                $mappedPort = (int) $portMappings[(string) $portNum];

                return sprintf('http://%s:%d', $publicIp, $mappedPort);
            }
        }

        if ($publicIp && isset($portMappings[(string) $privatePort])) {
            return sprintf('http://%s:%d', $publicIp, (int) $portMappings[(string) $privatePort]);
        }

        return sprintf('https://%s-%d.proxy.runpod.net', $podId, $privatePort);
    }

    /**
     * Find a serverless endpoint by name and return its runsync URL.
     * RunPod serverless API: https://api.runpod.ai/v2/{endpoint_id}/runsync
     *
     * @return array{url: string, endpoint_id: string}|null
     */
    public function getServerlessEndpointByName(string $endpointName): ?array
    {
        $endpoints = $this->client->listEndpoints();

        foreach ($endpoints as $ep) {
            if (($ep['name'] ?? '') === $endpointName && ! empty($ep['id'] ?? null)) {
                $endpointId = $ep['id'];

                return [
                    'url' => "https://api.runpod.ai/v2/{$endpointId}/runsync",
                    'endpoint_id' => $endpointId,
                ];
            }
        }

        return null;
    }
}
