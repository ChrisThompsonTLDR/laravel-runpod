<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Exceptions;

use Exception;

class RunPodPodUnavailableException extends Exception
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(
            'RunPod pod is unavailable. The pod may still be starting, or creation failed. Check RUNPOD_POD_IMAGE and RunPod API status.',
            0,
            $previous
        );
    }
}
