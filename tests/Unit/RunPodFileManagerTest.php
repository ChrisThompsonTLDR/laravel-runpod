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

it('syncFrom throws when file does not exist', function () {
    $manager = new RunPodFileManager(Storage::disk('runpod'), storage_path('app/runpod'), 'data');

    $manager->syncFrom('/nonexistent.pdf');
})->throws(\RuntimeException::class, 'Local file does not exist');

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

it('ensure throws when local file does not exist', function () {
    $loadPath = storage_path('app/runpod');
    if (! is_dir($loadPath)) {
        mkdir($loadPath, 0755, true);
    }

    $manager = new RunPodFileManager(Storage::disk('runpod'), $loadPath, 'data');
    $manager->ensure('nonexistent.pdf');
})->throws(\RuntimeException::class, 'Local file does not exist');

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

it('syncAll throws when load path is not directory', function () {
    $manager = new RunPodFileManager(Storage::disk('runpod'), '/nonexistent/path', 'data');

    $manager->syncAll();
})->throws(\RuntimeException::class, 'Load path is not a directory');

it('syncFrom is no-op when isLocal is true', function () {
    $loadPath = storage_path('app/runpod');
    if (! is_dir($loadPath)) {
        mkdir($loadPath, 0755, true);
    }
    file_put_contents($loadPath.'/doc.pdf', 'content');

    $manager = new RunPodFileManager(Storage::disk('runpod'), $loadPath, 'data', true);
    $manager->syncFrom($loadPath.'/doc.pdf');

    expect(Storage::disk('runpod')->exists('data/doc.pdf'))->toBeFalse();
});

it('syncAll is no-op when isLocal is true', function () {
    $loadPath = storage_path('app/runpod');
    if (! is_dir($loadPath)) {
        mkdir($loadPath, 0755, true);
    }
    file_put_contents($loadPath.'/a.txt', 'a');

    $manager = new RunPodFileManager(Storage::disk('runpod'), $loadPath, 'data', true);
    $manager->syncAll();

    expect(Storage::disk('runpod')->exists('data/a.txt'))->toBeFalse();
});

it('ensure is no-op when isLocal is true', function () {
    $loadPath = storage_path('app/runpod');
    if (! is_dir($loadPath)) {
        mkdir($loadPath, 0755, true);
    }
    file_put_contents($loadPath.'/doc.pdf', 'content');

    $manager = new RunPodFileManager(Storage::disk('runpod'), $loadPath, 'data', true);
    $manager->ensure('doc.pdf');

    expect(Storage::disk('runpod')->exists('data/doc.pdf'))->toBeFalse();
});

// =============================================================================
// path()
// =============================================================================

it('path returns remote path for relative input', function () {
    $manager = new RunPodFileManager(Storage::disk('runpod'), storage_path('app/runpod'), 'data');

    expect($manager->path('doc.pdf'))->toBe('data/doc.pdf')
        ->and($manager->path('sub/file.pdf'))->toBe('data/sub/file.pdf');
});

it('path returns input when already prefixed', function () {
    $manager = new RunPodFileManager(Storage::disk('runpod'), storage_path('app/runpod'), 'data');

    expect($manager->path('data/doc.pdf'))->toBe('data/doc.pdf');
});

// =============================================================================
// resolveLocalPath / relative path handling
// =============================================================================

it('syncFrom accepts relative path under load path', function () {
    $loadPath = storage_path('app/runpod');
    if (! is_dir($loadPath)) {
        mkdir($loadPath, 0755, true);
    }
    $subdir = $loadPath.'/sub';
    if (! is_dir($subdir)) {
        mkdir($subdir, 0755, true);
    }
    file_put_contents($subdir.'/doc.pdf', 'content');

    $manager = new RunPodFileManager(Storage::disk('runpod'), $loadPath, 'data');
    $manager->syncFrom('sub/doc.pdf');

    expect(Storage::disk('runpod')->get('data/sub/doc.pdf'))->toBe('content');
});

it('ensure accepts relative path under load path', function () {
    $loadPath = storage_path('app/runpod');
    if (! is_dir($loadPath)) {
        mkdir($loadPath, 0755, true);
    }
    $subdir = $loadPath.'/sub';
    if (! is_dir($subdir)) {
        mkdir($subdir, 0755, true);
    }
    file_put_contents($subdir.'/doc.pdf', 'content');

    $manager = new RunPodFileManager(Storage::disk('runpod'), $loadPath, 'data');
    $manager->ensure('sub/doc.pdf');

    expect(Storage::disk('runpod')->get('data/sub/doc.pdf'))->toBe('content');
});

it('get works with already prefixed path', function () {
    Storage::disk('runpod')->put('data/sub/file.pdf', 'content');
    $manager = new RunPodFileManager(Storage::disk('runpod'), storage_path('app/runpod'), 'data');

    expect($manager->get('data/sub/file.pdf'))->toBe('content');
});

it('returns chainable self from put syncFrom syncAll ensure', function () {
    $loadPath = storage_path('app/runpod');
    if (! is_dir($loadPath)) {
        mkdir($loadPath, 0755, true);
    }
    file_put_contents($loadPath.'/a.txt', 'a');

    $manager = new RunPodFileManager(Storage::disk('runpod'), $loadPath, 'data');

    expect($manager->put('x.txt', 'x'))->toBe($manager)
        ->and($manager->syncFrom($loadPath.'/a.txt'))->toBe($manager)
        ->and($manager->syncAll())->toBe($manager)
        ->and($manager->ensure('a.txt'))->toBe($manager);
});
