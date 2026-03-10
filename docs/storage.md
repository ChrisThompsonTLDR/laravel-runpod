# Storage

Laravel RunPod uses RunPod's S3-compatible API to manage files on network volumes. Files synced to the volume are available at `/workspace/{prefix}/` inside the pod.

## Load Path and Prefix

- **Load path** — Local directory to sync from (per-instance `load_path`, e.g. `storage_path('app/runpod')`)
- **Prefix** — Folder under volume root (`remote_disk.prefix`, e.g. `data`); maps to `/workspace/data/` on pods

Files under the load path map to `{prefix}/{relative_path}` on S3. On the pod, that becomes `/workspace/{prefix}/{relative_path}` (e.g. `/workspace/data/document.pdf`).

## Using the File Manager

Get a `RunPodFileManager` via `RunPod::disk()`:

```php
$fm = RunPod::disk('runpod');

// Ensure file exists (sync from load path if missing)
$fm->ensure('document.pdf');

// Sync specific file (full path or relative to load path)
$fm->syncFrom('/path/to/file.pdf');
$fm->syncFrom('subdir/file.pdf');

// Sync entire load path
$fm->syncAll();

// Direct write
$fm->put('data/output.pdf', $contents);

// Read
$contents = $fm->get('data/output.pdf');

// Exists
$fm->exists('data/document.pdf');

// Path for pod APIs (e.g. "data/doc.pdf")
$path = $fm->path('doc.pdf');
```

## Using Storage Directly

When the `runpod` disk is registered (S3 key, secret, and bucket set), you can use Laravel's Storage facade:

```php
use Illuminate\Support\Facades\Storage;

// Standard Laravel disk operations
Storage::disk('runpod')->put('data/file.pdf', $contents);
$contents = Storage::disk('runpod')->get('data/file.pdf');
Storage::disk('runpod')->exists('data/file.pdf');
```

Note: `Storage::disk('runpod')` returns a standard S3 `Filesystem` instance. For `ensure()`, `syncFrom()`, and `syncAll()`, use `RunPod::disk('runpod')` which returns `RunPodFileManager`.

## Scheduled Sync

Add to `routes/console.php`:

```php
Schedule::command('runpod:sync')->everyFiveMinutes();
```

## Storage Cost

RunPod network volume pricing starts at $0.07/GB/month. See [RunPod pricing](https://www.runpod.io/pricing) for current rates.
