<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ChrisThompsonTLDR\LaravelRunPod\RunPod for(string $nickname)
 * @method static \ChrisThompsonTLDR\LaravelRunPod\RunPodFileManager disk(?string $disk = null)
 * @method static \ChrisThompsonTLDR\LaravelRunPod\RunPod instance(string $name)
 * @method static array|null start()
 * @method static string|null url()
 * @method static \ChrisThompsonTLDR\LaravelRunPod\RunPod startWithPrune(string $scheduleMethod = 'everyFiveMinutes')
 *
 * @see \ChrisThompsonTLDR\LaravelRunPod\RunPod
 */
class RunPod extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ChrisThompsonTLDR\LaravelRunPod\RunPod::class;
    }
}
