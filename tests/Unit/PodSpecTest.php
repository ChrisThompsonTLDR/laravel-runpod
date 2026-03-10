<?php

use ChrisThompsonTLDR\LaravelRunPod\PodSpec;

covers(PodSpec::class);

// =============================================================================
// CPU spec (array format)
// =============================================================================

it('converts cpu spec array to api input', function () {
    $config = [
        'spec' => ['cpu5c-16-32', 'cpu5g-16-32'],
        'image_name' => 'nginx:alpine',
        'name' => 'test-pod',
    ];

    $result = PodSpec::toApiInput($config);

    expect($result)->toHaveKeys(['computeType', 'cpuFlavorIds', 'vcpuCount', 'volumeInGb', 'imageName', 'name'])
        ->and($result['computeType'])->toBe('CPU')
        ->and($result['cpuFlavorIds'])->toBe(['cpu5c', 'cpu5g'])
        ->and($result['vcpuCount'])->toBe(16)
        ->and($result['volumeInGb'])->toBe(32)
        ->and($result['imageName'])->toBe('nginx:alpine')
        ->and($result['name'])->toBe('test-pod');
});

it('converts wildcard spec to all cpu flavors', function () {
    $config = [
        'spec' => ['*'],
        'image_name' => 'nginx',
        'name' => 'pod',
    ];

    $result = PodSpec::toApiInput($config);

    expect($result['cpuFlavorIds'])->toBe(['cpu3c', 'cpu3g', 'cpu3m', 'cpu5c', 'cpu5g', 'cpu5m'])
        ->and($result['vcpuCount'])->toBe(16)
        ->and($result['volumeInGb'])->toBe(32);
});

it('uses defaults when spec has minimal config', function () {
    $config = [
        'spec' => ['cpu5c-8-20'],
        'image_name' => 'img',
    ];

    $result = PodSpec::toApiInput($config);

    expect($result['name'])->toBe('runpod-pod')
        ->and($result['ports'])->toBe(['80/http'])
        ->and($result['dataCenterIds'])->toBe(['US-MD-1'])
        ->and($result['cloudType'])->toBe('SECURE')
        ->and($result['containerDiskInGb'])->toBe(50);
});

// =============================================================================
// GPU / legacy path
// =============================================================================

it('converts gpu config to api input', function () {
    $config = [
        'gpu_count' => 1,
        'gpu_type_id' => 'NVIDIA A100',
        'image_name' => 'cuda',
        'name' => 'gpu-pod',
    ];

    $result = PodSpec::toApiInput($config);

    expect($result['computeType'])->toBe('GPU')
        ->and($result['gpuCount'])->toBe(1)
        ->and($result['gpuTypeIds'])->toBe(['NVIDIA A100'])
        ->and($result['minVcpuCount'])->toBe(2)
        ->and($result['minRAMPerGPU'])->toBe(15);
});

it('uses gpu defaults when gpu_type_id omitted', function () {
    $config = [
        'gpu_count' => 2,
        'image_name' => 'img',
    ];

    $result = PodSpec::toApiInput($config);

    expect($result['gpuTypeIds'])->toBe(['NVIDIA GeForce RTX 4090']);
});

// =============================================================================
// Optional fields
// =============================================================================

it('includes network_volume_id when set', function () {
    $config = [
        'spec' => ['cpu5c-16-32'],
        'image_name' => 'img',
        'network_volume_id' => 'vol-123',
    ];

    $result = PodSpec::toApiInput($config);

    expect($result['networkVolumeId'])->toBe('vol-123');
});

it('includes volume_mount_path when set', function () {
    $config = [
        'spec' => ['cpu5c-16-32'],
        'image_name' => 'img',
        'volume_mount_path' => '/workspace',
    ];

    $result = PodSpec::toApiInput($config);

    expect($result['volumeMountPath'])->toBe('/workspace');
});

it('converts env array to object', function () {
    $config = [
        'spec' => ['cpu5c-16-32'],
        'image_name' => 'img',
        'env' => [
            ['key' => 'FOO', 'value' => 'bar'],
            ['key' => 'NUM', 'value' => 42],
        ],
    ];

    $result = PodSpec::toApiInput($config);

    expect($result['env'])->toBe(['FOO' => 'bar', 'NUM' => '42']);
});

it('parses ports string to array', function () {
    $config = [
        'spec' => ['cpu5c-16-32'],
        'image_name' => 'img',
        'ports' => '80/http, 22/tcp',
    ];

    $result = PodSpec::toApiInput($config);

    expect($result['ports'])->toBe(['80/http', '22/tcp']);
});

it('accepts imageName as alias for image_name', function () {
    $config = [
        'spec' => ['cpu5c-16-32'],
        'imageName' => 'custom/image',
    ];

    $result = PodSpec::toApiInput($config);

    expect($result['imageName'])->toBe('custom/image');
});

// =============================================================================
// Filtering
// =============================================================================

it('filters out null values', function () {
    $config = [
        'spec' => ['cpu5c-16-32'],
        'image_name' => 'img',
    ];

    $result = PodSpec::toApiInput($config);

    expect(array_values($result))->not->toContain(null);
});

it('omits network_volume_id when empty', function () {
    $config = [
        'spec' => ['cpu5c-16-32'],
        'image_name' => 'img',
        'network_volume_id' => '',
    ];

    $result = PodSpec::toApiInput($config);

    expect($result)->not->toHaveKey('networkVolumeId');
});

// =============================================================================
// Edge cases
// =============================================================================

it('clamps vcpu to minimum 1', function () {
    $config = [
        'spec' => ['cpu5c-0-32'],
        'image_name' => 'img',
    ];

    $result = PodSpec::toApiInput($config);

    expect($result['vcpuCount'])->toBe(1);
});

it('clamps volume to minimum 0', function () {
    $config = [
        'spec' => ['cpu5c-16--5'],
        'image_name' => 'img',
    ];

    $result = PodSpec::toApiInput($config);

    expect($result['volumeInGb'])->toBe(0);
});

it('falls back to cpu5c for invalid flavor in spec', function () {
    $config = [
        'spec' => ['invalid-16-32'],
        'image_name' => 'img',
    ];

    $result = PodSpec::toApiInput($config);

    expect($result['cpuFlavorIds'])->toContain('cpu5c');
});
