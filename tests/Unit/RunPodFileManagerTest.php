<?php

use ChrisThompsonTLDR\LaravelRunPod\RunPodFileManager;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

uses(TestCase::class);

covers(RunPodFileManager::class);

beforeEach(function () {
    Storage::fake('runpod');
});

it('puts content at remote path with prefix', function () {
    $disk = Storage::disk('runpod');
    $manager = new RunPodFileManager($disk, storage_path('app/runpod'), 'data');

    $manager->put('file.pdf', 'content');

    expect($disk->exists('data/file.pdf'))->toBeTrue()
        ->and($disk->get('data/file.pdf'))->toBe('content');
});

it('gets content from remote path', function () {
    Storage::disk('runpod')->put('data/file.pdf', 'content');
    $manager = new RunPodFileManager(Storage::disk('runpod'), storage_path('app/runpod'), 'data');

    expect($manager->get('file.pdf'))->toBe('content');
});

it('exists returns true when file present', function () {
    Storage::disk('runpod')->put('data/file.pdf', 'content');
    $manager = new RunPodFileManager(Storage::disk('runpod'), storage_path('app/runpod'), 'data');

    expect($manager->exists('file.pdf'))->toBeTrue();
});

it('exists returns false when file absent', function () {
    $manager = new RunPodFileManager(Storage::disk('runpod'), storage_path('app/runpod'), 'data');

    expect($manager->exists('missing.pdf'))->toBeFalse();
});

it('syncFrom copies file from load path to remote', function () {
    $loadPath = storage_path('app/runpod');
    if (! is_dir($loadPath)) {
        mkdir($loadPath, 0755, true);
    }
    file_put_contents($loadPath.'/doc.pdf', 'content');

    $manager = new RunPodFileManager(Storage::disk('runpod'), $loadPath, 'data');
    $manager->syncFrom($loadPath.'/doc.pdf');

    expect(Storage::disk('runpod')->get('data/doc.pdf'))->toBe('content');
});

it('syncFrom returns self when file does not exist', function () {
    $manager = new RunPodFileManager(Storage::disk('runpod'), storage_path('app/runpod'), 'data');

    $result = $manager->syncFrom('/nonexistent.pdf');

    expect($result)->toBe($manager);
});

it('ensure syncs file when not present remotely', function () {
    $loadPath = storage_path('app/runpod');
    if (! is_dir($loadPath)) {
        mkdir($loadPath, 0755, true);
    }
    file_put_contents($loadPath.'/doc.pdf', 'content');

    $manager = new RunPodFileManager(Storage::disk('runpod'), $loadPath, 'data');
    $manager->ensure('doc.pdf');

    expect(Storage::disk('runpod')->get('data/doc.pdf'))->toBe('content');
});

it('ensure skips when file already exists remotely', function () {
    $loadPath = storage_path('app/runpod');
    if (! is_dir($loadPath)) {
        mkdir($loadPath, 0755, true);
    }
    file_put_contents($loadPath.'/doc.pdf', 'content');
    Storage::disk('runpod')->put('data/doc.pdf', 'existing');

    $manager = new RunPodFileManager(Storage::disk('runpod'), $loadPath, 'data');
    $manager->ensure('doc.pdf');

    expect(Storage::disk('runpod')->get('data/doc.pdf'))->toBe('existing');
});

it('preserves path when already prefixed', function () {
    $disk = Storage::disk('runpod');
    $manager = new RunPodFileManager($disk, storage_path('app/runpod'), 'data');

    $manager->put('data/sub/file.pdf', 'content');

    expect($disk->exists('data/sub/file.pdf'))->toBeTrue();
});

it('syncAll syncs all files from load path', function () {
    $loadPath = storage_path('app/runpod');
    if (! is_dir($loadPath)) {
        mkdir($loadPath, 0755, true);
    }
    $subdir = $loadPath.'/sub';
    if (! is_dir($subdir)) {
        mkdir($subdir, 0755, true);
    }
    file_put_contents($loadPath.'/a.txt', 'a');
    file_put_contents($subdir.'/b.txt', 'b');

    $manager = new RunPodFileManager(Storage::disk('runpod'), $loadPath, 'data');
    $manager->syncAll();

    expect(Storage::disk('runpod')->get('data/a.txt'))->toBe('a')
        ->and(Storage::disk('runpod')->get('data/sub/b.txt'))->toBe('b');
});

it('syncAll returns self when load path is not directory', function () {
    $manager = new RunPodFileManager(Storage::disk('runpod'), '/nonexistent/path', 'data');

    $result = $manager->syncAll();

    expect($result)->toBe($manager);
});
