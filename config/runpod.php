<?php

return [
    'disk' => 'runpod',

    'guardrails' => [
        'enabled' => true,
        'cache_schedule' => 'everyFifteenMinutes',
        'limits' => [
            'pods' => [
                'pods_max' => 10,
                'pods_running_max' => 5,
            ],
            'serverless' => [
                'endpoints_max' => 5,
                'workers_total_max' => 20,
            ],
            'storage' => [
                'network_volumes_max' => 5,
                'volume_size_gb_max' => 100,
            ],
        ],
    ],

    'load_path' => storage_path('app/runpod'),

    'remote_prefix' => 'data',

    's3' => [
        'key' => env('RUNPOD_S3_ACCESS_KEY'),
        'secret' => env('RUNPOD_S3_SECRET_KEY'),
        'region' => 'US-MD-1',
        'bucket' => null,
        'endpoint' => 'https://s3api-us-md-1.runpod.io',
    ],

    'api_key' => env('RUNPOD_API_KEY'),

    'state_file' => storage_path('app/runpod-pod-state.json'),

    'stats_file' => storage_path('app/runpod-stats.json'),

    'prune_schedule' => 'everyFiveMinutes',

    'instances' => [
        'pymupdf' => [
            'type' => 'pod',
            'prune_schedule' => 'everyFiveMinutes',
            'pod' => [
                'image_name' => 'ghcr.io/christhompsontldr/docker-pymupdf-tesseract:latest',
                'network_volume_id' => null,
                'data_center_ids' => ['US-MD-1'],
                'gpu_count' => 0,
                'name' => 'eyejay-pymupdf',
                'ports' => '8000/http',
                'volume_mount_path' => '/workspace',
                'env' => [
                    ['key' => 'PYMUPDF_DATA_DIR', 'value' => '/workspace'],
                    ['key' => 'PYMUPDF_OUTPUT_DIR', 'value' => '/workspace/output'],
                ],
            ],
        ],
    ],

    'pod' => [
        'inactivity_minutes' => 2,
        'gpu_type_id' => 'NVIDIA GeForce RTX 4090',
        'gpu_count' => 0,
        'volume_in_gb' => 50,
        'container_disk_in_gb' => 50,
        'min_vcpu_count' => 2,
        'min_memory_in_gb' => 15,
        'image_name' => 'ghcr.io/christhompsontldr/docker-pymupdf-tesseract:latest',
        'name' => 'eyejay-pymupdf',
        'ports' => '8000/http',
        'volume_mount_path' => '/workspace',
        'network_volume_id' => null,
        'cloud_type' => 'SECURE',
        'env' => [
            ['key' => 'PYMUPDF_DATA_DIR', 'value' => '/workspace'],
            ['key' => 'PYMUPDF_OUTPUT_DIR', 'value' => '/workspace/output'],
        ],
    ],
];
