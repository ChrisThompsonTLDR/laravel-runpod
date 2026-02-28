<?php

return [
    'disk' => env('RUNPOD_DISK', 'runpod'),

    /*
    |--------------------------------------------------------------------------
    | Guardrails
    |--------------------------------------------------------------------------
    |
    | Limits for RunPod API usage. When exceeded, GuardrailsExceededException
    | is thrown and GuardrailsTripped event is dispatched.
    | Usage is cached; cache_schedule controls TTL (default 15 min).
    |
    */
    'guardrails' => [
        'enabled' => env('RUNPOD_GUARDRAILS_ENABLED', true),
        'cache_schedule' => env('RUNPOD_GUARDRAILS_CACHE_SCHEDULE', 'everyFifteenMinutes'),
        'limits' => [
            'pods' => [
                'pods_max' => (int) env('RUNPOD_GUARDRAILS_PODS_MAX', 10),
                'pods_running_max' => (int) env('RUNPOD_GUARDRAILS_PODS_RUNNING_MAX', 5),
            ],
            'serverless' => [
                'endpoints_max' => (int) env('RUNPOD_GUARDRAILS_ENDPOINTS_MAX', 5),
                'workers_total_max' => (int) env('RUNPOD_GUARDRAILS_WORKERS_TOTAL_MAX', 20),
            ],
            'storage' => [
                'network_volumes_max' => (int) env('RUNPOD_GUARDRAILS_NETWORK_VOLUMES_MAX', 5),
                'volume_size_gb_max' => (int) env('RUNPOD_GUARDRAILS_VOLUME_SIZE_GB_MAX', 100),
            ],
        ],
    ],

    'load_path' => env('RUNPOD_LOAD_PATH', storage_path('app/runpod')),

    'remote_prefix' => env('RUNPOD_REMOTE_PREFIX', 'data'),

    's3' => [
        'key' => env('RUNPOD_S3_ACCESS_KEY'),
        'secret' => env('RUNPOD_S3_SECRET_KEY'),
        'region' => env('RUNPOD_S3_REGION', 'EU-RO-1'),
        'bucket' => env('RUNPOD_NETWORK_VOLUME_ID'),
        'endpoint' => env('RUNPOD_S3_ENDPOINT', 'https://s3api-eu-ro-1.runpod.io'),
    ],

    'api_key' => env('RUNPOD_API_KEY'),

    'state_file' => env('RUNPOD_STATE_FILE', storage_path('app/runpod-pod-state.json')),

    /*
    |--------------------------------------------------------------------------
    | Prune Schedule
    |--------------------------------------------------------------------------
    |
    | How often to run the runpod:prune command to terminate inactive pods.
    | Options: everyMinute, everyTwoMinutes, everyFiveMinutes, everyTenMinutes,
    |          everyFifteenMinutes, everyThirtyMinutes, hourly
    |
    */
    'prune_schedule' => env('RUNPOD_PRUNE_SCHEDULE', 'everyFiveMinutes'),

    /*
    |--------------------------------------------------------------------------
    | Instances (Pods / Serverless)
    |--------------------------------------------------------------------------
    |
    | Named instances for different workloads. Each can be type 'pod' or 'serverless'.
    | Pods use prune_schedule; serverless uses idleTimeout (built-in).
    |
    */
    'instances' => [
        'pymupdf' => [
            'type' => 'pod',
            'prune_schedule' => 'everyFiveMinutes',
            'pod' => [
                'gpu_count' => 0,
                'name' => env('RUNPOD_POD_NAME', 'eyejay-pymupdf'),
                'ports' => env('RUNPOD_POD_PORTS', '8000/http'),
                'volume_mount_path' => env('RUNPOD_POD_VOLUME_MOUNT', '/workspace'),
                'env' => [
                    ['key' => 'PYMUPDF_DATA_DIR', 'value' => '/workspace'],
                    ['key' => 'PYMUPDF_OUTPUT_DIR', 'value' => '/workspace/output'],
                ],
            ],
        ],
    ],

    'pod' => [
        'inactivity_minutes' => (int) env('RUNPOD_POD_INACTIVITY_MINUTES', 2),
        'gpu_type_id' => env('RUNPOD_POD_GPU_TYPE_ID', 'NVIDIA GeForce RTX 4090'),
        'gpu_count' => (int) env('RUNPOD_POD_GPU_COUNT', 0),
        'volume_in_gb' => (int) env('RUNPOD_POD_VOLUME_GB', 50),
        'container_disk_in_gb' => (int) env('RUNPOD_POD_CONTAINER_DISK_GB', 50),
        'min_vcpu_count' => (int) env('RUNPOD_POD_MIN_VCPU', 2),
        'min_memory_in_gb' => (int) env('RUNPOD_POD_MIN_MEMORY_GB', 15),
        'image_name' => env('RUNPOD_POD_IMAGE'),
        'name' => env('RUNPOD_POD_NAME', 'eyejay-pymupdf'),
        'ports' => env('RUNPOD_POD_PORTS', '8000/http'),
        'volume_mount_path' => env('RUNPOD_POD_VOLUME_MOUNT', '/workspace'),
        'network_volume_id' => env('RUNPOD_NETWORK_VOLUME_ID'),
        'cloud_type' => env('RUNPOD_POD_CLOUD_TYPE', 'SECURE'),
        'env' => [
            ['key' => 'PYMUPDF_DATA_DIR', 'value' => '/workspace'],
            ['key' => 'PYMUPDF_OUTPUT_DIR', 'value' => '/workspace/output'],
        ],
    ],
];
