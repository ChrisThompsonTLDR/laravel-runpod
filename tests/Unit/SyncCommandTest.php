<?php

use ChrisThompsonTLDR\LaravelRunPod\Console\SyncCommand;
use ChrisThompsonTLDR\LaravelRunPod\Facades\RunPod;
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

    RunPod::shouldReceive('disk')->once()->andReturn($mockFileManager);

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

    config(['runpod.load_path' => $loadPath]);

    $exitCode = Artisan::call('runpod:sync', ['--path' => 'doc.pdf']);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Synced: doc.pdf');

    expect(Storage::disk('runpod')->exists('data/doc.pdf'))->toBeTrue();
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

    config(['runpod.load_path' => $loadPath]);

    $exitCode = Artisan::call('runpod:sync', ['--path' => 'subdir/']);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Synced 2 files from: subdir/');
});

it('returns failure when path not found', function () {
    config(['runpod.load_path' => storage_path('app/runpod')]);

    $exitCode = Artisan::call('runpod:sync', ['--path' => 'nonexistent.pdf']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Path not found');
});
