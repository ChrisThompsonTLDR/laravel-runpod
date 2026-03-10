<?php

use ChrisThompsonTLDR\LaravelRunPod\Concerns\RefreshesRunPod;
use ChrisThompsonTLDR\LaravelRunPod\RunPod;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;

uses(TestCase::class);

covers(RefreshesRunPod::class);

// =============================================================================
// runPodInstance() type and default
// =============================================================================

it('runPodInstance returns string', function () {
    $job = new RefreshesRunPodJob;
    $method = (new \ReflectionClass($job))->getMethod('runPodInstance');
    $method->setAccessible(true);

    $result = $method->invoke($job);

    expect($result)->toBeString();
})->group('type');

it('runPodInstance returns default by default', function () {
    $job = new RefreshesRunPodJob;
    $method = (new \ReflectionClass($job))->getMethod('runPodInstance');
    $method->setAccessible(true);

    expect($method->invoke($job))->toBe('default');
});

it('runPodInstance can be overridden', function () {
    $job = new CustomInstanceJob;
    $method = (new \ReflectionClass($job))->getMethod('runPodInstance');
    $method->setAccessible(true);

    expect($method->invoke($job))->toBe('custom');
});

// =============================================================================
// withRunPodRefresh() behavior
// =============================================================================

it('returns work result when work succeeds', function () {
    $mockRunPod = \Mockery::mock(RunPod::class);
    $mockRunPod->shouldReceive('instance')->with('default')->once()->andReturnSelf();
    $mockRunPod->shouldReceive('for')->with(RefreshesRunPodJob::class)->once();

    app()->instance(RunPod::class, $mockRunPod);

    $job = new RefreshesRunPodJob;
    $result = $job->runWork(fn () => 'result');

    expect($result)->toBe('result');
});

it('calls RunPod instance and for in finally when work succeeds', function () {
    $mockRunPod = \Mockery::mock(RunPod::class);
    $mockRunPod->shouldReceive('instance')->with('default')->once()->andReturnSelf();
    $mockRunPod->shouldReceive('for')->with(RefreshesRunPodJob::class)->once();

    app()->instance(RunPod::class, $mockRunPod);

    $job = new RefreshesRunPodJob;
    $job->runWork(fn () => 'ok');

    $mockRunPod->mockery_verify();
});

it('calls RunPod for even when work throws', function () {
    $mockRunPod = \Mockery::mock(RunPod::class);
    $mockRunPod->shouldReceive('instance')->with('custom')->once()->andReturnSelf();
    $mockRunPod->shouldReceive('for')->with(CustomInstanceJob::class)->once();

    app()->instance(RunPod::class, $mockRunPod);

    $job = new CustomInstanceJob;

    expect(fn () => $job->runWork(fn () => throw new \RuntimeException('fail')))
        ->toThrow(\RuntimeException::class, 'fail');

    $mockRunPod->mockery_verify();
});

it('passes correct instance name from runPodInstance to RunPod', function () {
    $mockRunPod = \Mockery::mock(RunPod::class);
    $mockRunPod->shouldReceive('instance')->with('my-instance')->once()->andReturnSelf();
    $mockRunPod->shouldReceive('for')->with(ExplicitInstanceJob::class)->once();

    app()->instance(RunPod::class, $mockRunPod);

    $job = new ExplicitInstanceJob;
    $job->runWork(fn () => null);
});

it('passes static::class to RunPod for', function () {
    $mockRunPod = \Mockery::mock(RunPod::class);
    $mockRunPod->shouldReceive('instance')->andReturnSelf();
    $mockRunPod->shouldReceive('for')->with(RefreshesRunPodJob::class)->once();

    app()->instance(RunPod::class, $mockRunPod);

    (new RefreshesRunPodJob)->runWork(fn () => null);
});

it('calls instance before for', function () {
    $callOrder = [];
    $mockRunPod = \Mockery::mock(RunPod::class);
    $mock = $mockRunPod;
    $mockRunPod->shouldReceive('instance')->with('default')->once()->andReturnUsing(function () use (&$callOrder, $mock) {
        $callOrder[] = 'instance';

        return $mock;
    });
    $mockRunPod->shouldReceive('for')->with(RefreshesRunPodJob::class)->once()->andReturnUsing(function () use (&$callOrder, $mock) {
        $callOrder[] = 'for';

        return $mock;
    });

    app()->instance(RunPod::class, $mockRunPod);

    (new RefreshesRunPodJob)->runWork(fn () => null);

    expect($callOrder)->toBe(['instance', 'for']);
});

// =============================================================================
// withRunPodRefresh() return type (mixed)
// =============================================================================

it('returns string from work', function () {
    app()->instance(RunPod::class, mockRunPod());

    $job = new RefreshesRunPodJob;
    $result = $job->runWork(fn () => 'hello');

    expect($result)->toBe('hello');
})->group('type');

it('returns int from work', function () {
    app()->instance(RunPod::class, mockRunPod());

    $job = new RefreshesRunPodJob;
    $result = $job->runWork(fn () => 42);

    expect($result)->toBe(42);
})->group('type');

it('returns array from work', function () {
    app()->instance(RunPod::class, mockRunPod());

    $job = new RefreshesRunPodJob;
    $result = $job->runWork(fn () => ['a' => 1]);

    expect($result)->toBe(['a' => 1]);
})->group('type');

it('returns null from work', function () {
    app()->instance(RunPod::class, mockRunPod());

    $job = new RefreshesRunPodJob;
    $result = $job->runWork(fn () => null);

    expect($result)->toBeNull();
})->group('type');

it('returns object from work', function () {
    app()->instance(RunPod::class, mockRunPod());

    $job = new RefreshesRunPodJob;
    $obj = new \stdClass;
    $obj->id = 1;
    $result = $job->runWork(fn () => $obj);

    expect($result)->toBe($obj)->and($result->id)->toBe(1);
})->group('type');

// =============================================================================
// callback behavior
// =============================================================================

it('invokes callback with no arguments', function () {
    app()->instance(RunPod::class, mockRunPod());

    $receivedArgs = null;
    $job = new RefreshesRunPodJob;
    $job->runWork(function (...$args) use (&$receivedArgs) {
        $receivedArgs = $args;

        return 'ok';
    });

    expect($receivedArgs)->toBe([]);
});

it('invokes callback exactly once', function () {
    app()->instance(RunPod::class, mockRunPod());

    $invokeCount = 0;
    $job = new RefreshesRunPodJob;
    $job->runWork(function () use (&$invokeCount) {
        $invokeCount++;

        return 'ok';
    });

    expect($invokeCount)->toBe(1);
});

// =============================================================================
// RunPod resolution
// =============================================================================

it('resolves RunPod from container', function () {
    $mockRunPod = \Mockery::mock(RunPod::class);
    $mockRunPod->shouldReceive('instance')->andReturnSelf();
    $mockRunPod->shouldReceive('for')->once();

    app()->instance(RunPod::class, $mockRunPod);

    $job = new RefreshesRunPodJob;
    $job->runWork(fn () => 'ok');

    expect(app(RunPod::class))->toBe($mockRunPod);
});

// =============================================================================
// Helpers and test classes
// =============================================================================

function mockRunPod()
{
    $mock = \Mockery::mock(RunPod::class);
    $mock->shouldReceive('instance')->andReturnSelf();
    $mock->shouldReceive('for')->andReturnSelf();

    return $mock;
}

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

class ExplicitInstanceJob
{
    use RefreshesRunPod;

    protected function runPodInstance(): string
    {
        return 'my-instance';
    }

    public function runWork(callable $work): mixed
    {
        return $this->withRunPodRefresh($work);
    }
}
