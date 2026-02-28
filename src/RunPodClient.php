<?php

namespace ChrisThompsonTLDR\LaravelRunPod;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Laravel HTTP Client wrapper for the RunPod REST API.
 *
 * Covers all endpoints from https://rest.runpod.io/v1 including:
 * - Pods
 * - Serverless Endpoints
 * - Network Volumes
 * - Templates
 * - Container Registry Auths
 * - Billing
 */
class RunPodClient
{
    public function __construct(
        protected string $apiKey,
        protected string $baseUrl = 'https://rest.runpod.io/v1'
    ) {}

    // -------------------------------------------------------------------------
    // Pods
    // -------------------------------------------------------------------------

    /**
     * List all pods. Optional filters: computeType, cpuFlavorId, dataCenterId, etc.
     */
    public function listPods(array $filters = []): array
    {
        $response = $this->http()->get('/pods', $filters);

        return $response->successful() ? ($response->json() ?? []) : [];
    }

    /**
     * Get a pod by ID.
     */
    public function getPod(string $podId): ?array
    {
        $response = $this->http()->get("/pods/{$podId}");

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Create a new pod.
     *
     * @param  array  $input  PodCreateInput fields (imageName required)
     */
    public function createPod(array $input): ?array
    {
        $response = $this->http()->post('/pods', $input);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Update a pod by ID.
     */
    public function updatePod(string $podId, array $input): ?array
    {
        $response = $this->http()->patch("/pods/{$podId}", $input);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Delete (terminate) a pod by ID.
     */
    public function deletePod(string $podId): bool
    {
        $response = $this->http()->delete("/pods/{$podId}");

        return $response->status() === 204;
    }

    /**
     * Start or resume a pod.
     */
    public function startPod(string $podId): ?array
    {
        $response = $this->http()->post("/pods/{$podId}/start");

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Stop a pod.
     */
    public function stopPod(string $podId): ?array
    {
        $response = $this->http()->post("/pods/{$podId}/stop");

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Reset a pod (stop and restart with same config).
     */
    public function resetPod(string $podId): ?array
    {
        $response = $this->http()->post("/pods/{$podId}/reset");

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Restart a pod.
     */
    public function restartPod(string $podId): ?array
    {
        $response = $this->http()->post("/pods/{$podId}/restart");

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    // -------------------------------------------------------------------------
    // Serverless Endpoints
    // -------------------------------------------------------------------------

    /**
     * List all serverless endpoints.
     */
    public function listEndpoints(): array
    {
        $response = $this->http()->get('/endpoints');

        return $response->successful() ? ($response->json() ?? []) : [];
    }

    /**
     * Get a serverless endpoint by ID.
     */
    public function getEndpoint(string $endpointId): ?array
    {
        $response = $this->http()->get("/endpoints/{$endpointId}");

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Create a new serverless endpoint.
     *
     * @param  array  $input  EndpointCreateInput fields (templateId required)
     */
    public function createEndpoint(array $input): ?array
    {
        $response = $this->http()->post('/endpoints', $input);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Update a serverless endpoint by ID.
     */
    public function updateEndpoint(string $endpointId, array $input): ?array
    {
        $response = $this->http()->patch("/endpoints/{$endpointId}", $input);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Delete a serverless endpoint by ID.
     */
    public function deleteEndpoint(string $endpointId): bool
    {
        $response = $this->http()->delete("/endpoints/{$endpointId}");

        return $response->status() === 204;
    }

    // -------------------------------------------------------------------------
    // Network Volumes
    // -------------------------------------------------------------------------

    /**
     * List all network volumes.
     */
    public function listNetworkVolumes(): array
    {
        $response = $this->http()->get('/networkvolumes');

        return $response->successful() ? ($response->json() ?? []) : [];
    }

    /**
     * Get a network volume by ID.
     */
    public function getNetworkVolume(string $volumeId): ?array
    {
        $response = $this->http()->get("/networkvolumes/{$volumeId}");

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Create a new network volume.
     *
     * @param  array  $input  NetworkVolumeCreateInput fields (dataCenterId, name, size required)
     */
    public function createNetworkVolume(array $input): ?array
    {
        $response = $this->http()->post('/networkvolumes', $input);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Update a network volume by ID.
     */
    public function updateNetworkVolume(string $volumeId, array $input): ?array
    {
        $response = $this->http()->patch("/networkvolumes/{$volumeId}", $input);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Delete a network volume by ID.
     */
    public function deleteNetworkVolume(string $volumeId): bool
    {
        $response = $this->http()->delete("/networkvolumes/{$volumeId}");

        return $response->status() === 204;
    }

    // -------------------------------------------------------------------------
    // Templates
    // -------------------------------------------------------------------------

    /**
     * List all templates.
     */
    public function listTemplates(): array
    {
        $response = $this->http()->get('/templates');

        return $response->successful() ? ($response->json() ?? []) : [];
    }

    /**
     * Get a template by ID.
     */
    public function getTemplate(string $templateId): ?array
    {
        $response = $this->http()->get("/templates/{$templateId}");

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Create a new template.
     *
     * @param  array  $input  TemplateCreateInput fields (imageName and name required)
     */
    public function createTemplate(array $input): ?array
    {
        $response = $this->http()->post('/templates', $input);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Update a template by ID.
     */
    public function updateTemplate(string $templateId, array $input): ?array
    {
        $response = $this->http()->patch("/templates/{$templateId}", $input);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Delete a template by ID.
     */
    public function deleteTemplate(string $templateId): bool
    {
        $response = $this->http()->delete("/templates/{$templateId}");

        return $response->status() === 204;
    }

    // -------------------------------------------------------------------------
    // Container Registry Auths
    // -------------------------------------------------------------------------

    /**
     * List all container registry auths.
     */
    public function listContainerRegistryAuths(): array
    {
        $response = $this->http()->get('/containerregistryauth');

        return $response->successful() ? ($response->json() ?? []) : [];
    }

    /**
     * Get a container registry auth by ID.
     */
    public function getContainerRegistryAuth(string $authId): ?array
    {
        $response = $this->http()->get("/containerregistryauth/{$authId}");

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Create a new container registry auth.
     *
     * @param  array  $input  ContainerRegistryAuthCreateInput fields (name, password, username required)
     */
    public function createContainerRegistryAuth(array $input): ?array
    {
        $response = $this->http()->post('/containerregistryauth', $input);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Delete a container registry auth by ID.
     */
    public function deleteContainerRegistryAuth(string $authId): bool
    {
        $response = $this->http()->delete("/containerregistryauth/{$authId}");

        return $response->status() === 204;
    }

    // -------------------------------------------------------------------------
    // Billing
    // -------------------------------------------------------------------------

    /**
     * Get pod billing history.
     *
     * @param  array  $params  Optional query params (startDate, endDate, podId, gpuTypeId, groupBy, etc.)
     */
    public function getPodBilling(array $params = []): array
    {
        $response = $this->http()->get('/billing/pods', $params);

        return $response->successful() ? ($response->json() ?? []) : [];
    }

    /**
     * Get serverless endpoint billing history.
     *
     * @param  array  $params  Optional query params (startDate, endDate, endpointId, gpuTypeId, groupBy, etc.)
     */
    public function getEndpointBilling(array $params = []): array
    {
        $response = $this->http()->get('/billing/endpoints', $params);

        return $response->successful() ? ($response->json() ?? []) : [];
    }

    /**
     * Get network volume billing history.
     *
     * @param  array  $params  Optional query params (startDate, endDate, networkVolumeId, groupBy, etc.)
     */
    public function getNetworkVolumeBilling(array $params = []): array
    {
        $response = $this->http()->get('/billing/networkvolumes', $params);

        return $response->successful() ? ($response->json() ?? []) : [];
    }

    // -------------------------------------------------------------------------
    // HTTP client factory
    // -------------------------------------------------------------------------

    protected function http(): PendingRequest
    {
        return Http::withToken($this->apiKey)
            ->baseUrl($this->baseUrl)
            ->acceptJson();
    }
}
