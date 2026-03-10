<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Concerns;

use ChrisThompsonTLDR\LaravelRunPod\RunPod;

/**
 * Trait for jobs that use RunPod. Refreshes last_run_at in a finally block
 * after your pod work completes, keeping the pod alive for the prune timer.
 *
 * Override runPodInstance() to return your instance name (must exist in config).
 * Use withRunPodRefresh() to wrap the work that uses the pod.
 */
trait RefreshesRunPod
{
    /**
     * The RunPod instance name (e.g. 'example'). Override in your job.
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
