<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Concerns;

use ChrisThompsonTLDR\LaravelRunPod\RunPod;

/**
 * Trait for jobs that use RunPod. Automatically refreshes last_run_at after
 * pod work completes, keeping the pod alive for the prune timer.
 *
 * Use withRunPodRefresh() to wrap your post-pod work; for() fires in finally.
 */
trait RefreshesRunPod
{
    /**
     * The RunPod instance name (e.g. 'pymupdf'). Override in your job.
     */
    protected function runPodInstance(): string
    {
        return 'default';
    }

    /**
     * Wrap RunPod work; for() is called automatically when the callback finishes.
     */
    protected function withRunPodRefresh(callable $work): mixed
    {
        try {
            return $work();
        } finally {
            app(RunPod::class)
                ->instance($this->runPodInstance())
                ->for(static::class);
        }
    }
}
