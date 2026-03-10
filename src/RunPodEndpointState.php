<?php

namespace ChrisThompsonTLDR\LaravelRunPod;

/**
 * Manages serverless endpoint state in JSON files (mirrors pod state pattern).
 *
 * State file: runpod-endpoint-state-{instance}.json
 * Contents: { endpoint_id, url, endpoint_name }
 */
class RunPodEndpointState
{
    public function __construct() {}

    /**
     * Read endpoint state for an instance.
     *
     * @return array{endpoint_id: string, url: string, endpoint_name?: string}|null
     */
    public function read(string $instance): ?array
    {
        $path = $this->resolvePath($instance);

        if (! $path || ! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) && ! empty($data['endpoint_id'] ?? null) ? $data : null;
    }

    /**
     * Write endpoint state for an instance.
     *
     * @param  array{endpoint_id: string, url: string, endpoint_name?: string}  $state
     */
    public function write(string $instance, array $state): void
    {
        $path = $this->resolvePath($instance);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Get endpoint_id for an instance (from state only).
     */
    public function getEndpointId(string $instance): ?string
    {
        $state = $this->read($instance);

        return $state['endpoint_id'] ?? null;
    }

    protected function resolvePath(string $instance): string
    {
        $config = config("runpod.instances.{$instance}", []);
        if (! empty($config['endpoint_state_file'])) {
            $path = $config['endpoint_state_file'];

            return str_starts_with($path, '/') ? $path : base_path($path);
        }

        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $instance);

        return storage_path("app/runpod-endpoint-state-{$safe}.json");
    }
}
