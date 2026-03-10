<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Exceptions;

use Exception;

class RunPodPodUnavailableException extends Exception
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(
            'RunPod pod unavailable. Pod may still be starting or creation failed. Check config/runpod.php (image_name) and RunPod API status.',
            0,
            $previous
        );
    }
}
