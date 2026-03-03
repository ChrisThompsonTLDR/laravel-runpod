<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RunPod Dashboard</title>
    @fluxAppearance
</head>
<body class="antialiased">
    <div class="min-h-screen bg-zinc-50 dark:bg-zinc-900 p-8">
        <div class="mx-auto max-w-6xl">
            <h1 class="mb-8 text-2xl font-bold">RunPod Dashboard</h1>
            @livewire('runpod-dashboard', ['instance' => $instance])
        </div>
    </div>
    @fluxScripts
</body>
</html>
