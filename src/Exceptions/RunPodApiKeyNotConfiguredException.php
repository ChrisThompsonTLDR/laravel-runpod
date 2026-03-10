<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Exceptions;

use Exception;

class RunPodApiKeyNotConfiguredException extends Exception
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(
            'RunPod API key not configured. Set RUNPOD_API_KEY in .env.',
            0,
            $previous
        );
    }
}
