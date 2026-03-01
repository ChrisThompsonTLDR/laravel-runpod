<div wire:poll.15s="refresh" class="space-y-6">
    @if ($stats)
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <flux:card>
                <flux:heading size="lg">{{ $stats['pod']['name'] ?? $stats['pod']['id'] ?? 'Unknown' }}</flux:heading>
                <flux:text>Pod</flux:text>
            </flux:card>
            <flux:card>
                <flux:heading size="lg">{{ $stats['pod']['desiredStatus'] ?? '-' }}</flux:heading>
                <flux:text>Status</flux:text>
            </flux:card>
            <flux:card>
                <flux:heading size="lg">${{ $stats['pod']['costPerHr'] ?? $stats['pod']['adjustedCostPerHr'] ?? '-' }}/hr</flux:heading>
                <flux:text>Cost</flux:text>
            </flux:card>
            <flux:card>
                <flux:heading size="lg" class="tabular-nums">{{ $stats['time_until_kill'] ?? '--:--:--' }}</flux:heading>
                <flux:text>Time until prune</flux:text>
            </flux:card>
        </div>

        <flux:card>
            <flux:heading size="lg">Specs</flux:heading>
            <dl class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">vCPUs</dt>
                    <dd class="font-medium">{{ $stats['pod']['vcpuCount'] ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">Memory</dt>
                    <dd class="font-medium">{{ $stats['pod']['memoryInGb'] ?? '-' }} GB</dd>
                </div>
                <div>
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">GPU</dt>
                    <dd class="font-medium">
                        @if (! empty($stats['pod']['gpu']))
                            {{ $stats['pod']['gpu']['count'] ?? 1 }}x {{ $stats['pod']['gpu']['displayName'] ?? $stats['pod']['gpu']['id'] ?? 'GPU' }}
                        @else
                            none
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">Storage</dt>
                    <dd class="font-medium">{{ $stats['pod']['containerDiskInGb'] ?? '-' }} GB container / {{ $stats['pod']['volumeInGb'] ?? '-' }} GB volume</dd>
                </div>
            </dl>
        </flux:card>

        @php
            $telemetry = $stats['telemetry'] ?? [];
            $cpu = $telemetry['cpuUtilization'] ?? null;
            $mem = $telemetry['memoryUtilization'] ?? null;
            $gpuMetrics = $telemetry['averageGpuMetrics'] ?? $telemetry['individualGpuMetrics'][0] ?? null;
            $gpuPct = $gpuMetrics['percentUtilization'] ?? null;
            $temp = $gpuMetrics['temperatureCelcius'] ?? null;
        @endphp
        <flux:card>
            <flux:heading size="lg">Telemetry</flux:heading>
            <dl class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">CPU</dt>
                    <dd class="font-medium">{{ $cpu !== null ? round($cpu * 100) . '%' : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">Memory</dt>
                    <dd class="font-medium">{{ $mem !== null ? round($mem * 100) . '%' : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">GPU</dt>
                    <dd class="font-medium">{{ $gpuPct !== null ? round($gpuPct) . '%' : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">GPU Temp</dt>
                    <dd class="font-medium">{{ $temp !== null ? $temp . '°C' : '-' }}</dd>
                </div>
            </dl>
        </flux:card>
    @else
        <flux:card>
            <flux:heading size="lg">No data</flux:heading>
            <flux:text class="mt-2">
                Run <code>php artisan runpod:stats {{ $instance }}</code> or <code>php artisan runpod:start {{ $instance }}</code> to populate the dashboard.
            </flux:text>
        </flux:card>
    @endif
</div>
