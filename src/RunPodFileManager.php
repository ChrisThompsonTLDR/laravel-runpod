<?php

namespace ChrisThompsonTLDR\LaravelRunPod;

use Illuminate\Contracts\Filesystem\Filesystem;
use League\Flysystem\UnableToCheckFileExistence;

class RunPodFileManager
{
    public function __construct(
        protected Filesystem $disk,
        protected string $loadPath,
        protected string $remotePrefix
    ) {}

    public function put(string $path, $contents): self
    {
        $this->disk->put($this->remotePath($path), $contents);

        return $this;
    }

    public function get(string $path): string
    {
        return $this->disk->get($this->remotePath($path));
    }

    public function exists(string $path): bool
    {
        return $this->disk->exists($this->remotePath($path));
    }

    public function syncFrom(string $localPath): self
    {
        $fullPath = $this->resolveLocalPath($localPath);

        if (! is_file($fullPath)) {
            return $this;
        }

        $relativePath = $this->relativeToLoadPath($fullPath);
        $remotePath = $this->remotePrefix.'/'.$relativePath;

        $this->disk->put($remotePath, file_get_contents($fullPath));

        return $this;
    }

    public function syncAll(): self
    {
        if (! is_dir($this->loadPath)) {
            return $this;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->loadPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $this->syncFrom($file->getPathname());
            }
        }

        return $this;
    }

    /**
     * Get the storage path for a file (e.g. "data/doc.pdf").
     * Use when calling pod APIs that expect the path as seen on the mounted volume.
     */
    public function path(string $path): string
    {
        return $this->remotePath($path);
    }

    public function ensure(string $path): self
    {
        $localPath = $this->resolveLocalPath($path);

        if (! is_file($localPath)) {
            return $this;
        }

        $relativePath = $this->relativeToLoadPath($localPath);
        $remotePath = $this->remotePrefix.'/'.$relativePath;

        $exists = false;
        try {
            $exists = $this->disk->exists($remotePath);
        } catch (UnableToCheckFileExistence $e) {
            // S3/network unreachable or misconfigured; assume missing and sync
        }

        if (! $exists) {
            $this->disk->put($remotePath, file_get_contents($localPath));
        }

        return $this;
    }

    protected function remotePath(string $path): string
    {
        if (str_starts_with($path, $this->remotePrefix.'/')) {
            return $path;
        }

        return $this->remotePrefix.'/'.ltrim($path, '/');
    }

    protected function resolveLocalPath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:#', $path)) {
            return $path;
        }

        return rtrim($this->loadPath, '/').'/'.ltrim($path, '/');
    }

    protected function relativeToLoadPath(string $fullPath): string
    {
        $loadPath = realpath($this->loadPath);

        if ($loadPath === false) {
            return basename($fullPath);
        }

        $resolvedFullPath = realpath($fullPath) ?: $fullPath;

        return ltrim(str_replace($loadPath, '', $resolvedFullPath), '/');
    }
}
