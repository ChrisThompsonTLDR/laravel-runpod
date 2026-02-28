<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Macros;

use ChrisThompsonTLDR\LaravelRunPod\RunPodFileManager;
use Illuminate\Support\Facades\Storage;

class StorageMacros
{
    public static function register(): void
    {
        Storage::macro('runpod', function () {
            return new RunPodFileManager(
                Storage::disk(config('runpod.disk', 'runpod')),
                config('runpod.load_path', storage_path('app/runpod')),
                config('runpod.remote_prefix', 'data')
            );
        });
    }
}
