<?php

namespace ChrisThompsonTLDR\LaravelRunPod;

/**
 * Pod lifecycle client. Delegates all HTTP operations to RunPodClient.
 */
class RunPodPodClient
{
    public function __construct(
        protected RunPodClient $client
    ) {}

    public function createPod(array $input): ?array
    {
        return $this->client->createPod($input);
    }

    public function getPod(string $podId): ?array
    {
        return $this->client->getPod($podId);
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
     */
    public function getPublicUrl(string $podId, int $privatePort = 8000): ?string
    {
        $pod = $this->getPod($podId);
        $ports = $pod['ports'] ?? [];

        foreach ($ports as $portSpec) {
            if (is_string($portSpec) && str_contains($portSpec, '/') && str_ends_with($portSpec, '/http')) {
                $portNum = (int) explode('/', $portSpec)[0];

                return sprintf('https://%s-%d.proxy.runpod.net', $podId, $portNum ?: $privatePort);
            }
        }

        return sprintf('https://%s-%d.proxy.runpod.net', $podId, $privatePort);
    }
}
