<?php

namespace ChrisThompsonTLDR\LaravelRunPod;

/**
 * Converts instance config to RunPod API PodCreateInput.
 *
 * spec: Array of flavor-vcpu-volume (e.g. ['cpu5c-16-32', 'cpu5g-16-32']). Use ['*'] for any flavor.
 * GPU: Use gpu_count, gpu_type_id, volume_in_gb instead of spec.
 */
class PodSpec
{
    protected static array $cpuFlavors = ['cpu3c', 'cpu3g', 'cpu3m', 'cpu5c', 'cpu5g', 'cpu5m'];

    /**
     * Convert config to RunPod API PodCreateInput.
     */
    public static function toApiInput(array $config): array
    {
        $spec = $config['spec'] ?? null;

        if ($spec !== null && self::isCpuSpec($spec)) {
            [$flavors, $vcpu, $volume] = self::resolveSpec($spec);
            $input = [
                'computeType' => 'CPU',
                'cpuFlavorIds' => $flavors,
                'vcpuCount' => max(1, $vcpu),
                'volumeInGb' => max(0, $volume),
                'containerDiskInGb' => 50,
                'dataCenterIds' => $config['data_center_ids'] ?? ['US-MD-1'],
                'cloudType' => $config['cloud_type'] ?? 'SECURE',
            ];
        } else {
            $input = self::gpuOrLegacyCpuToApiInput($config);
        }

        $input['imageName'] = $config['image_name'] ?? $config['imageName'] ?? null;
        $input['name'] = $config['name'] ?? 'runpod-pod';
        $input['ports'] = is_string($config['ports'] ?? null)
            ? explode(',', str_replace(' ', '', $config['ports']))
            : ($config['ports'] ?? ['80/http']);

        if (! empty($config['network_volume_id'])) {
            $input['networkVolumeId'] = $config['network_volume_id'];
        }
        if (! empty($config['volume_mount_path'])) {
            $input['volumeMountPath'] = $config['volume_mount_path'];
        }
        if (isset($config['env']) && is_array($config['env'])) {
            $input['env'] = self::envToObject($config['env']);
        }

        return array_filter($input, fn ($v) => $v !== null);
    }

    protected static function isCpuSpec(mixed $spec): bool
    {
        if (is_array($spec) && ! empty($spec)) {
            $first = $spec[0];

            return $first === '*' || self::isCpuFlavor((string) (explode('-', (string) $first)[0]));
        }
        if (is_string($spec)) {
            return self::isCpuFlavor((string) (explode('-', $spec)[0]));
        }

        return false;
    }

    protected static function isCpuFlavor(string $s): bool
    {
        return in_array($s, self::$cpuFlavors, true);
    }

    /**
     * Resolve spec to [flavors, vcpu, volume]. Supports ['cpu5c-16-32', 'cpu5g-16-32'] or ['*'].
     */
    protected static function resolveSpec(mixed $spec): array
    {
        if (is_string($spec)) {
            $parts = explode('-', $spec);
            $flavor = (string) $parts[0];

            return [
                self::isCpuFlavor($flavor) ? [$flavor] : ['cpu5c'],
                max(1, (int) ($parts[1] ?? 16)),
                max(0, (int) ($parts[2] ?? 32)),
            ];
        }
        if (is_array($spec) && ! empty($spec)) {
            if (in_array('*', $spec, true)) {
                return [self::$cpuFlavors, 16, 32];
            }
            $flavors = [];
            $vcpu = 16;
            $volume = 32;
            foreach ($spec as $s) {
                $parts = explode('-', (string) $s);
                $flavor = (string) $parts[0];
                if (self::isCpuFlavor($flavor)) {
                    $flavors[] = $flavor;
                    if (isset($parts[1])) {
                        $vcpu = max(1, (int) $parts[1]);
                    }
                    if (isset($parts[2])) {
                        $volume = max(0, (int) $parts[2]);
                    }
                }
            }

            return [array_unique($flavors) ?: ['cpu5c'], $vcpu, $volume];
        }

        return [['cpu5c'], 16, 32];
    }

    protected static function gpuOrLegacyCpuToApiInput(array $config): array
    {
        $gpuCount = (int) ($config['gpu_count'] ?? 0);
        $isGpu = $gpuCount > 0;

        $input = [
            'computeType' => $isGpu ? 'GPU' : 'CPU',
            'volumeInGb' => (int) ($config['volume_in_gb'] ?? 20),
            'containerDiskInGb' => (int) ($config['container_disk_in_gb'] ?? 50),
            'dataCenterIds' => $config['data_center_ids'] ?? ['US-MD-1'],
            'cloudType' => $config['cloud_type'] ?? 'SECURE',
        ];

        if ($isGpu) {
            $input['gpuCount'] = $gpuCount;
            $input['gpuTypeIds'] = [$config['gpu_type_id'] ?? 'NVIDIA GeForce RTX 4090'];
            $input['minVcpuCount'] = (int) ($config['min_vcpu_count'] ?? 2);
            $input['minRAMPerGPU'] = (int) ($config['min_memory_in_gb'] ?? 15);
        } else {
            $input['cpuFlavorIds'] = [$config['cpu_flavor'] ?? 'cpu5c'];
            $input['vcpuCount'] = (int) ($config['vcpu_count'] ?? $config['min_vcpu_count'] ?? 2);
        }

        return $input;
    }

    protected static function envToObject(array $env): array
    {
        $out = [];
        foreach ($env as $item) {
            if (isset($item['key'], $item['value'])) {
                $out[$item['key']] = (string) $item['value'];
            }
        }

        return $out;
    }
}
