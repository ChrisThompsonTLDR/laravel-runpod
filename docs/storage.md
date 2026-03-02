# Storage

Laravel RunPod uses RunPod's S3-compatible API to manage files on network volumes. Files synced to the volume are available at `/workspace/{remote_prefix}/` inside the pod.

## Load Path and Remote Prefix

- **Load path** — Local directory to sync from (`config/runpod.load_path`, default `storage/app/runpod`)
- **Remote prefix** — S3 key prefix (`config/runpod.remote_prefix`, default `data`)

Files under the load path map to `{remote_prefix}/{relative_path}` on S3. On the pod, that becomes `/workspace/{remote_prefix}/{relative_path}` (e.g. `/workspace/data/document.pdf`).

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
