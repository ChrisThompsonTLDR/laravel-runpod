<?php

namespace Chris\LaravelRunPod\Macros;

use Chris\LaravelRunPod\RunPodFileManager;
use Illuminate\Support\Facades\Storage;

class StorageMacros
{
    public static function register(): void
    {
        Storage::macro('runpod', function () {
            return new RunPodFileManager(
                Storage::disk(config('runpod.disk', 'runpod')),
                config('runpod.load_path', storage_path('app/insurance-journals')),
                config('runpod.remote_prefix', 'data')
            );
        });
    }
}
