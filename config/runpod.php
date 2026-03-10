<?php

return [
    'disk' => 'runpod',

    'dashboard' => [
        'middleware' => ['web', 'can:viewRunpod'],
    ],

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

    'api_key' => env('RUNPOD_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Instances (pods/serverless/local)
    |--------------------------------------------------------------------------
    |
    | Each instance has type (pod|serverless|local), storage, and pod params.
    | Pod params: spec, image_name, name, ports, inactivity_minutes, etc.
    |
    */
    'instances' => [
        'example' => [
            'type' => 'pod',
            'load_path' => storage_path('app/runpod'),
            'local_disk' => 'runpod_local',
            'state_file' => storage_path('app/runpod-pod-state-example.json'),
            'stats_file' => storage_path('app/runpod-stats-example.json'),
            'remote_disk' => [
                'disk_name' => 'runpod',
                'prefix' => 'data',
                'key' => env('RUNPOD_S3_ACCESS_KEY'),
                'secret' => env('RUNPOD_S3_SECRET_KEY'),
                'region' => 'US-MD-1',
                'bucket' => env('RUNPOD_NETWORK_VOLUME_ID'),
                'endpoint' => 'https://s3api-us-md-1.runpod.io',
            ],
            'prune_schedule' => 'everyFiveMinutes',
            'inactivity_minutes' => 2,
            'spec' => ['cpu5c-16-32', 'cpu5g-16-32'],
            'image_name' => 'nginx:alpine',
            'name' => 'runpod-example',
            'ports' => '80/http',
        ],
        'example-local' => [
            'type' => 'local',
            'load_path' => storage_path('app/runpod'),
            'local_disk' => 'runpod_local',
            'local_url' => 'http://example:80',
            'state_file' => storage_path('app/runpod-pod-state-example-local.json'),
            'stats_file' => storage_path('app/runpod-stats-example-local.json'),
            'image_name' => 'nginx:alpine',
            'name' => 'runpod-example-local',
            'ports' => '80/http',
        ],
        'example-serverless' => [
            'type' => 'serverless',
            'load_path' => storage_path('app/runpod'),
            'remote_disk' => [
                'disk_name' => 'runpod',
                'prefix' => 'data',
                'key' => env('RUNPOD_S3_ACCESS_KEY'),
                'secret' => env('RUNPOD_S3_SECRET_KEY'),
                'region' => 'US-MD-1',
                'bucket' => env('RUNPOD_NETWORK_VOLUME_ID'),
                'endpoint' => 'https://s3api-us-md-1.runpod.io',
            ],
            'endpoint_state_file' => storage_path('app/runpod-endpoint-state-example-serverless.json'),
            'stats_file' => storage_path('app/runpod-stats-example-serverless.json'),
            'serverless' => [
                'endpoint_name' => 'runpod-example-serverless',
            ],
        ],
    ],
];
