<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SyncCommand extends Command
{
    protected $signature = 'runpod:sync
        {--path= : Sync a specific file or directory relative to load_path}';

    protected $description = 'Sync files from load path to RunPod network volume';

    public function handle(): int
    {
        $path = $this->option('path');

        if ($path) {
            $fullPath = rtrim(config('runpod.load_path'), '/').'/'.ltrim($path, '/');

            if (is_file($fullPath)) {
                Storage::runpod()->syncFrom($fullPath);
                $this->info("Synced: {$path}");
            } elseif (is_dir($fullPath)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
                $count = 0;
                foreach ($files as $file) {
                    if ($file->isFile()) {
                        Storage::runpod()->syncFrom($file->getPathname());
                        $count++;
                    }
                }
                $this->info("Synced {$count} files from: {$path}");
            } else {
                $this->error("Path not found: {$fullPath}");

                return self::FAILURE;
            }
        } else {
            Storage::runpod()->syncAll();
            $this->info('Synced entire load path to RunPod.');
        }

        return self::SUCCESS;
    }
}
