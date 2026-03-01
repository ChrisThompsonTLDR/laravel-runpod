<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Console;

use ChrisThompsonTLDR\LaravelRunPod\RunPodStatsWriter;
use Illuminate\Console\Command;

class DashboardCommand extends Command
{
    protected $signature = 'runpod:dashboard
        {instance? : Instance name (e.g. pymupdf). Omit for default.}
        {--refresh=5 : Refresh interval in seconds}';

    protected $description = 'Live RunPod stats dashboard (reads from stats file)';

    public function handle(RunPodStatsWriter $statsWriter): int
    {
        if (! function_exists('Termwind\Live\live')) {
            $this->error('Termwind Live Plugin is required for the dashboard. Install it with:');
            $this->line('  composer require nunomaduro/termwind xico2k/termwind-plugin-live');

            return self::FAILURE;
        }

        $instance = $this->argument('instance') ?? 'default';
        $refresh = (int) $this->option('refresh') ?: 5;

        $live = \Termwind\Live\live(function () use ($statsWriter, $instance) {
            return $this->renderDashboard($statsWriter->read($instance));
        });

        $live->refreshEvery(seconds: $refresh)->hideCursor();

        return self::SUCCESS;
    }

    protected function renderDashboard(?array $stats): string
    {
        if (! $stats) {
            return $this->renderEmpty();
        }

        $pod = $stats['pod'] ?? [];
        $telemetry = $stats['telemetry'] ?? [];
        $timeUntilKill = $stats['time_until_kill'] ?? '--:--:--';
        $instance = $stats['instance'] ?? 'unknown';

        $name = $pod['name'] ?? $pod['id'] ?? 'unknown';
        $status = $pod['desiredStatus'] ?? '-';
        $cost = $pod['costPerHr'] ?? $pod['adjustedCostPerHr'] ?? '-';

        $vcpus = $pod['vcpuCount'] ?? '-';
        $memory = $pod['memoryInGb'] ?? '-';
        $gpu = $this->formatGpu($pod);
        $containerDisk = $pod['containerDiskInGb'] ?? '-';
        $volume = $pod['volumeInGb'] ?? '-';
        $networkVolume = $this->formatNetworkVolume($pod);

        $cpu = $telemetry['cpuUtilization'] ?? null;
        $mem = $telemetry['memoryUtilization'] ?? null;
        $gpuMetrics = $telemetry['averageGpuMetrics'] ?? $telemetry['individualGpuMetrics'][0] ?? null;
        $gpuPct = $gpuMetrics['percentUtilization'] ?? null;
        $temp = $gpuMetrics['temperatureCelcius'] ?? null;

        $cpuStr = $cpu !== null ? round($cpu * 100).'%' : '-';
        $memStr = $mem !== null ? round($mem * 100).'%' : '-';
        $gpuStr = $gpuPct !== null ? round($gpuPct).'%' : '-';
        $tempStr = $temp !== null ? $temp.'°C' : '-';

        $lines = [
            '┌─ RunPod Dashboard: '.$instance.' ─'.str_repeat('─', max(0, 40 - strlen($instance))).'┐',
            '│ Pod: '.$name.str_repeat(' ', max(0, 40 - strlen($name))).'│',
            '│ Status: '.$status.'  │  $'.($cost).'/hr  │  Time until prune: '.$timeUntilKill.' │',
            '├'.str_repeat('─', 58).'┤',
            '│ Specs:'.str_repeat(' ', 51).'│',
            '│   vCPUs: '.$vcpus.'    Memory: '.$memory.' GB    GPU: '.$gpu.str_repeat(' ', max(0, 20 - strlen($gpu))).'│',
            '│   Container: '.$containerDisk.' GB    Volume: '.$volume.' GB    Network: '.$networkVolume.str_repeat(' ', max(0, 15 - strlen($networkVolume))).'│',
            '├'.str_repeat('─', 58).'┤',
            '│ Telemetry:'.str_repeat(' ', 47).'│',
            '│   CPU: '.$cpuStr.'    Memory: '.$memStr.'    GPU: '.$gpuStr.'    Temp: '.$tempStr.str_repeat(' ', max(0, 15 - strlen($tempStr))).'│',
            '└'.str_repeat('─', 58).'┘',
        ];

        return implode("\n", $lines);
    }

    protected function renderEmpty(): string
    {
        $lines = [
            '┌─ RunPod Dashboard ─────────────────────────────────────────────────┐',
            '│ No data. Run: php artisan runpod:stats [instance]                    │',
            '│ Or: php artisan runpod:start [instance]                             │',
            '└'.str_repeat('─', 68).'┘',
        ];

        return implode("\n", $lines);
    }

    protected function formatGpu(array $pod): string
    {
        $gpu = $pod['gpu'] ?? null;
        if (! $gpu) {
            return 'none';
        }
        $count = $gpu['count'] ?? 1;
        $name = $gpu['displayName'] ?? $gpu['id'] ?? 'GPU';

        return $count.'x '.$name;
    }

    protected function formatNetworkVolume(array $pod): string
    {
        $nv = $pod['networkVolume'] ?? null;
        if (! $nv) {
            return 'none';
        }

        return ($nv['size'] ?? '?').' GB';
    }
}
