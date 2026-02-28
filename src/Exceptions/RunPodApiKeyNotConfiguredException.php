<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Exceptions;

use Exception;

class RunPodApiKeyNotConfiguredException extends Exception
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(
            'RunPod API key is not configured. Set RUNPOD_API_KEY in your .env file or disable RunPod usage (e.g. eyejay.pymupdf.use_runpod=false).',
            0,
            $previous
        );
    }
}
