<?php

use ChrisThompsonTLDR\LaravelRunPod\Console\SyncCommand;
use ChrisThompsonTLDR\LaravelRunPod\RunPod;
use ChrisThompsonTLDR\LaravelRunPod\RunPodFileManager;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(TestCase::class);

covers(SyncCommand::class);

beforeEach(function () {
    Storage::fake('runpod');
});

it('calls syncAll and outputs success when no path option', function () {
    $mockFileManager = \Mockery::mock(RunPodFileManager::class);
    $mockFileManager->shouldReceive('syncAll')->once()->andReturnSelf();

    $mockRunPod = \Mockery::mock(RunPod::class);
    $mockRunPod->shouldReceive('disk')->withNoArgs()->once()->andReturn($mockFileManager);

    app()->instance(RunPod::class, $mockRunPod);

    $exitCode = Artisan::call('runpod:sync');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Synced entire load path to RunPod');
});

it('syncs single file when path is file', function () {
    $loadPath = storage_path('app/runpod');
    if (! is_dir($loadPath)) {
        mkdir($loadPath, 0755, true);
    }
    file_put_contents($loadPath.'/doc.pdf', 'content');

    $mockFileManager = \Mockery::mock(RunPodFileManager::class);
    $mockFileManager->shouldReceive('syncFrom')->with(\Mockery::on(fn ($p) => str_ends_with($p, 'doc.pdf')))->once();

    $mockRunPod = \Mockery::mock(RunPod::class);
    $mockRunPod->shouldReceive('disk')->withNoArgs()->andReturn($mockFileManager);

    app()->instance(RunPod::class, $mockRunPod);

    $exitCode = Artisan::call('runpod:sync', ['--path' => 'doc.pdf']);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Synced: doc.pdf');
});

it('syncs directory when path is directory', function () {
    $loadPath = storage_path('app/runpod');
    if (! is_dir($loadPath)) {
        mkdir($loadPath, 0755, true);
    }
    $subdir = $loadPath.'/subdir';
    if (! is_dir($subdir)) {
        mkdir($subdir, 0755, true);
    }
    file_put_contents($loadPath.'/subdir/a.txt', 'a');
    file_put_contents($loadPath.'/subdir/b.txt', 'b');

    $mockFileManager = \Mockery::mock(RunPodFileManager::class);
    $mockFileManager->shouldReceive('syncFrom')->andReturnSelf();

    $mockRunPod = \Mockery::mock(RunPod::class);
    $mockRunPod->shouldReceive('disk')->withNoArgs()->andReturn($mockFileManager);

    app()->instance(RunPod::class, $mockRunPod);

    $exitCode = Artisan::call('runpod:sync', ['--path' => 'subdir/']);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Synced 2 files from: subdir/');
});

it('returns failure when path not found', function () {
    $mockFileManager = \Mockery::mock(RunPodFileManager::class);
    $mockRunPod = \Mockery::mock(RunPod::class);
    $mockRunPod->shouldReceive('disk')->withNoArgs()->andReturn($mockFileManager);
    app()->instance(RunPod::class, $mockRunPod);

    $exitCode = Artisan::call('runpod:sync', ['--path' => 'nonexistent.pdf']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Path not found');
});
