<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Console;

use ChrisThompsonTLDR\LaravelRunPod\Facades\RunPod;
use Illuminate\Console\Command;

class SyncCommand extends Command
{
    protected $signature = 'runpod:sync
        {--path= : Sync a specific file or directory relative to load_path}';

    protected $description = 'Sync files from load path to RunPod network volume';

    public function handle(): int
    {
        $path = $this->option('path');

        if ($path) {
            if (str_contains($path, '..')) {
                $this->error('Invalid path: path traversal is not allowed.');

                return self::FAILURE;
            }

            $basePath = rtrim(config('runpod.load_path'), '/');
            $fullPath = $basePath.'/'.ltrim(str_replace('\\', '/', $path), '/');
            $resolvedFull = realpath($fullPath);
            $resolvedBase = realpath($basePath) ?: $basePath;

            if ($resolvedFull !== false && ! str_starts_with($resolvedFull, $resolvedBase.DIRECTORY_SEPARATOR) && $resolvedFull !== $resolvedBase) {
                $this->error('Invalid path: path must be within the configured load path.');

                return self::FAILURE;
            }

            $fullPath = $resolvedFull ?: $fullPath;

            if (is_file($fullPath)) {
                RunPod::disk()->syncFrom($fullPath);
                $this->info('Synced: '.$path);
            } elseif (is_dir($fullPath)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
                $count = 0;
                foreach ($files as $file) {
                    if ($file->isFile()) {
                        RunPod::disk()->syncFrom($file->getPathname());
                        $count++;
                    }
                }
                $this->info("Synced {$count} files from: {$path}");
            } else {
                $this->error("Path not found: {$fullPath}");

                return self::FAILURE;
            }
        } else {
            RunPod::disk()->syncAll();
            $this->info('Synced entire load path to RunPod.');
        }

        return self::SUCCESS;
    }
}
