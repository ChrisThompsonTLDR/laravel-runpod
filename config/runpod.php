<?php

return [
    'disk' => env('RUNPOD_DISK', 'runpod'),

    'load_path' => env('RUNPOD_LOAD_PATH', storage_path('app/insurance-journals')),

    'remote_prefix' => env('RUNPOD_REMOTE_PREFIX', 'data'),

    's3' => [
        'key' => env('RUNPOD_S3_ACCESS_KEY'),
        'secret' => env('RUNPOD_S3_SECRET_KEY'),
        'region' => env('RUNPOD_S3_REGION', 'EU-RO-1'),
        'bucket' => env('RUNPOD_NETWORK_VOLUME_ID'),
        'endpoint' => env('RUNPOD_S3_ENDPOINT', 'https://s3api-eu-ro-1.runpod.io'),
    ],
];
