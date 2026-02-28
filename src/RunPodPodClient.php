<?php

namespace ChrisThompsonTLDR\LaravelRunPod;

use Illuminate\Support\Facades\Http;

class RunPodPodClient
{
    public function __construct(
        protected string $apiKey,
        protected string $graphqlUrl = 'https://api.runpod.io/graphql',
        protected string $restUrl = 'https://rest.runpod.io/v1'
    ) {}

    public function createPod(array $input): ?array
    {
        $mutation = <<<'GQL'
mutation PodFindAndDeployOnDemand($input: PodFindAndDeployOnDemandInput!) {
  podFindAndDeployOnDemand(input: $input) {
    id
    name
    imageName
    machineId
    desiredStatus
    runtime {
      ports {
        ip
        isIpPublic
        privatePort
        publicPort
        type
      }
    }
  }
}
GQL;

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->apiKey,
        ])->post($this->graphqlUrl, [
            'query' => $mutation,
            'variables' => ['input' => $input],
        ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json('data.podFindAndDeployOnDemand');

        return is_array($data) ? $data : null;
    }

    public function getPod(string $podId): ?array
    {
        $query = <<<'GQL'
query Pod($podId: String!) {
  pod(input: { podId: $podId }) {
    id
    name
    desiredStatus
    runtime {
      uptimeInSeconds
      ports {
        ip
        isIpPublic
        privatePort
        publicPort
        type
      }
    }
  }
}
GQL;

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->apiKey,
        ])->post($this->graphqlUrl, [
            'query' => $query,
            'variables' => ['podId' => $podId],
        ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json('data.pod');

        return is_array($data) ? $data : null;
    }

    public function stopPod(string $podId): bool
    {
        $mutation = <<<'GQL'
mutation PodStop($input: PodStopInput!) {
  podStop(input: $input) {
    id
    desiredStatus
  }
}
GQL;

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->apiKey,
        ])->post($this->graphqlUrl, [
            'query' => $mutation,
            'variables' => ['input' => ['podId' => $podId]],
        ]);

        return $response->successful();
    }

    public function terminatePod(string $podId): bool
    {
        $response = Http::withToken($this->apiKey)
            ->delete("{$this->restUrl}/pods/{$podId}");

        return $response->status() === 204;
    }

    public function getMyself(): ?array
    {
        $query = <<<'GQL'
query Myself {
  myself {
    pods { id desiredStatus }
    endpoints { id workersMax workersMin }
    networkVolumes { id volumeInGb }
  }
}
GQL;

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->apiKey,
        ])->post($this->graphqlUrl, [
            'query' => $query,
        ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json('data.myself');
    }

    public function getPublicUrl(string $podId, int $privatePort = 8000): ?string
    {
        $pod = $this->getPod($podId);
        $ports = $pod['runtime']['ports'] ?? [];

        foreach ($ports as $port) {
            if (($port['type'] ?? '') === 'http') {
                $portNum = (int) ($port['privatePort'] ?? $port['publicPort'] ?? $privatePort);

                return sprintf('https://%s-%d.proxy.runpod.net', $podId, $portNum ?: $privatePort);
            }
        }

        return sprintf('https://%s-%d.proxy.runpod.net', $podId, $privatePort);
    }
}
