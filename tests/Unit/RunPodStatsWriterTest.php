<?php

use Carbon\Carbon;
use ChrisThompsonTLDR\LaravelRunPod\RunPodStatsWriter;
use ChrisThompsonTLDR\LaravelRunPod\Tests\TestCase;

uses(TestCase::class);

covers(RunPodStatsWriter::class);

it('write creates stats file with expected structure', function () {
    $instance = 'test-instance';
    $pod = ['id' => 'pod-1', 'desiredStatus' => 'RUNNING'];
    $telemetry = ['cpuUtilization' => 50];
    $lastRunAt = now()->toIso8601String();

    $writer = new RunPodStatsWriter;
    $writer->write($instance, $pod, $telemetry, $lastRunAt);

    $data = $writer->read($instance);

    expect($data)->toBeArray()
        ->and($data['instance'])->toBe($instance)
        ->and($data['pod'])->toBe($pod)
        ->and($data['telemetry'])->toBe($telemetry)
        ->and($data['last_run_at'])->toBe($lastRunAt)
        ->and($data)->toHaveKeys(['updated_at', 'time_until_kill', 'inactivity_minutes']);
});

it('read returns null when instance is null', function () {
    $writer = new RunPodStatsWriter;

    expect($writer->read(null))->toBeNull();
});

it('read returns null when file does not exist', function () {
    $writer = new RunPodStatsWriter;

    expect($writer->read('nonexistent-instance'))->toBeNull();
});

it('flush removes single instance file', function () {
    $instance = 'flush-test';
    $writer = new RunPodStatsWriter;
    $writer->write($instance, ['id' => 'x'], null, null);

    expect($writer->read($instance))->not->toBeNull();

    $writer->flush($instance);

    expect($writer->read($instance))->toBeNull();
});

it('flush null removes all configured instance files', function () {
    $statsDir = storage_path('app/runpod-test');
    if (! is_dir($statsDir)) {
        mkdir($statsDir, 0755, true);
    }
    $path1 = $statsDir.'/stats1.json';
    $path2 = $statsDir.'/stats2.json';

    config([
        'runpod.instances' => [
            'flush-a' => ['stats_file' => $path1],
            'flush-b' => ['stats_file' => $path2],
        ],
    ]);

    $writer = new RunPodStatsWriter;
    $writer->write('flush-a', ['id' => 'a'], null, null);
    $writer->write('flush-b', ['id' => 'b'], null, null);

    expect(file_exists($path1))->toBeTrue()
        ->and(file_exists($path2))->toBeTrue();

    $writer->flush(null);

    expect(file_exists($path1))->toBeFalse()
        ->and(file_exists($path2))->toBeFalse();
});

it('time_until_kill is 00:00:00 when lastRunAt is null', function () {
    $instance = 'kill-null';
    $writer = new RunPodStatsWriter;
    $writer->write($instance, ['id' => 'x'], null, null);

    $data = $writer->read($instance);

    expect($data['time_until_kill'])->toBe('00:00:00');
});

it('time_until_kill is 00:00:00 when past threshold', function () {
    $instance = 'kill-past';
    $lastRunAt = now()->subMinutes(10)->toIso8601String();

    $writer = new RunPodStatsWriter;
    $writer->write($instance, ['id' => 'x'], null, $lastRunAt, 5);

    $data = $writer->read($instance);

    expect($data['time_until_kill'])->toBe('00:00:00');
});

it('time_until_kill returns hh:mm:ss when within threshold', function () {
    Carbon::setTestNow('2024-01-15 12:00:00');
    $instance = 'kill-within';
    $lastRunAt = now()->subMinutes(1)->toIso8601String(); // 11:59

    $writer = new RunPodStatsWriter;
    $writer->write($instance, ['id' => 'x'], null, $lastRunAt, 5);

    $data = $writer->read($instance);

    expect($data['time_until_kill'])->toMatch('/^\d{2}:\d{2}:\d{2}$/')
        ->and($data['time_until_kill'])->not->toBe('00:00:00');
})->afterEach(fn () => Carbon::setTestNow());

it('uses stats_file from config when present', function () {
    $customPath = storage_path('app/custom-stats.json');
    config(['runpod.instances.custom-instance' => ['stats_file' => $customPath]]);

    $writer = new RunPodStatsWriter;
    $writer->write('custom-instance', ['id' => 'x'], null, null);

    expect(file_exists($customPath))->toBeTrue()
        ->and($writer->read('custom-instance'))->not->toBeNull();
});

it('inactivity_minutes defaults to 2', function () {
    $instance = 'inactivity-default';
    $writer = new RunPodStatsWriter;
    $writer->write($instance, ['id' => 'x'], null, null);

    $data = $writer->read($instance);

    expect($data['inactivity_minutes'])->toBe(2);
});

it('inactivity_minutes uses provided value', function () {
    $instance = 'inactivity-custom';
    $writer = new RunPodStatsWriter;
    $writer->write($instance, ['id' => 'x'], null, null, 10);

    $data = $writer->read($instance);

    expect($data['inactivity_minutes'])->toBe(10);
});

it('time_until_kill returns 00:00:00 when lastRunAt is invalid', function () {
    $instance = 'kill-invalid';
    $writer = new RunPodStatsWriter;
    $writer->write($instance, ['id' => 'x'], null, 'not-a-date', 5);

    $data = $writer->read($instance);

    expect($data['time_until_kill'])->toBe('00:00:00');
});

it('read returns null when file contains invalid json', function () {
    $path = storage_path('app/runpod-stats-invalid.json');
    config(['runpod.instances.invalid-json' => ['stats_file' => $path]]);
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($path, 'not valid json');

    $writer = new RunPodStatsWriter;

    expect($writer->read('invalid-json'))->toBeNull();
});
