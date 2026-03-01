<?php

use ChrisThompsonTLDR\LaravelRunPod\Concerns\RefreshesRunPod;
use ChrisThompsonTLDR\LaravelRunPod\RunPod;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;

uses(TestCase::class);

covers(RefreshesRunPod::class);

it('returns work result and calls RunPod for in finally', function () {
    $mockRunPod = \Mockery::mock(RunPod::class);
    $mockRunPod->shouldReceive('instance')->with('default')->once()->andReturnSelf();
    $mockRunPod->shouldReceive('for')->with(RefreshesRunPodJob::class)->once();

    app()->instance(RunPod::class, $mockRunPod);

    $job = new RefreshesRunPodJob;
    $result = $job->runWork(fn () => 'result');

    expect($result)->toBe('result');
});

it('calls RunPod for even when work throws', function () {
    $mockRunPod = \Mockery::mock(RunPod::class);
    $mockRunPod->shouldReceive('instance')->with('custom')->once()->andReturnSelf();
    $mockRunPod->shouldReceive('for')->with(CustomInstanceJob::class)->once();

    app()->instance(RunPod::class, $mockRunPod);

    $job = new CustomInstanceJob;

    expect(fn () => $job->runWork(fn () => throw new \RuntimeException('fail')))
        ->toThrow(\RuntimeException::class);
});

it('runPodInstance returns default by default', function () {
    $job = new RefreshesRunPodJob;
    $method = (new \ReflectionClass($job))->getMethod('runPodInstance');
    $method->setAccessible(true);

    expect($method->invoke($job))->toBe('default');
});

class RefreshesRunPodJob
{
    use RefreshesRunPod;

    public function runWork(callable $work): mixed
    {
        return $this->withRunPodRefresh($work);
    }
}

class CustomInstanceJob
{
    use RefreshesRunPod;

    protected function runPodInstance(): string
    {
        return 'custom';
    }

    public function runWork(callable $work): mixed
    {
        return $this->withRunPodRefresh($work);
    }
}
