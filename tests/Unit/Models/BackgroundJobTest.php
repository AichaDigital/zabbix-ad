<?php

use App\Models\BackgroundJob;
use App\Models\ZabbixConnection;

uses()->group('unit', 'models');

test('can create background job', function () {
    $connection = ZabbixConnection::factory()->create();
    $job = BackgroundJob::factory()->create([
        'zabbix_connection_id' => $connection->id,
        'job_type' => 'sync_templates',
        'status' => 'pending',
    ]);

    expect($job)
        ->toBeInstanceOf(BackgroundJob::class)
        ->job_type->toBe('sync_templates')
        ->status->toBe('pending')
        ->zabbix_connection_id->toBe($connection->id);
});

test('background job belongs to connection', function () {
    $connection = ZabbixConnection::factory()->create();
    $job = BackgroundJob::factory()->create([
        'zabbix_connection_id' => $connection->id,
    ]);

    $job->load('zabbixConnection');

    expect($job->zabbixConnection)
        ->toBeInstanceOf(ZabbixConnection::class)
        ->id->toBe($connection->id);
});

test('background job has default values', function () {
    $job = BackgroundJob::factory()->pending()->create();

    expect($job)
        ->status->toBe('pending')
        ->progress_percentage->toBe(0)
        ->error_message->toBeNull();
});

test('scope pending returns only pending jobs', function () {
    BackgroundJob::factory()->create(['status' => 'pending']);
    BackgroundJob::factory()->create(['status' => 'running']);

    $pendingJobs = BackgroundJob::pending()->get();

    expect($pendingJobs)
        ->toHaveCount(1)
        ->and($pendingJobs->first()->status)
        ->toBe('pending');
});

test('scope running returns only running jobs', function () {
    BackgroundJob::factory()->create(['status' => 'pending']);
    BackgroundJob::factory()->create(['status' => 'running']);

    $runningJobs = BackgroundJob::running()->get();

    expect($runningJobs)
        ->toHaveCount(1)
        ->and($runningJobs->first()->status)
        ->toBe('running');
});

test('scope completed returns only completed jobs', function () {
    BackgroundJob::factory()->create(['status' => 'completed']);
    BackgroundJob::factory()->create(['status' => 'failed']);

    $completedJobs = BackgroundJob::completed()->get();

    expect($completedJobs)
        ->toHaveCount(1)
        ->and($completedJobs->first()->status)
        ->toBe('completed');
});

test('scope failed returns only failed jobs', function () {
    BackgroundJob::factory()->create(['status' => 'completed']);
    BackgroundJob::factory()->create(['status' => 'failed']);

    $failedJobs = BackgroundJob::failed()->get();

    expect($failedJobs)
        ->toHaveCount(1)
        ->and($failedJobs->first()->status)
        ->toBe('failed');
});

test('scope by type returns jobs by type', function () {
    BackgroundJob::factory()->create(['job_type' => 'sync_templates']);
    BackgroundJob::factory()->create(['job_type' => 'optimize_templates']);

    $syncJobs = BackgroundJob::byType('sync_templates')->get();
    $optimizeJobs = BackgroundJob::byType('optimize_templates')->get();

    expect($syncJobs)
        ->toHaveCount(1)
        ->and($syncJobs->first()->job_type)
        ->toBe('sync_templates')
        ->and($optimizeJobs)
        ->toHaveCount(1)
        ->and($optimizeJobs->first()->job_type)
        ->toBe('optimize_templates');
});

test('background job model structure matches expected schema', function () {
    $job = BackgroundJob::factory()->make([
        'job_type' => 'test_job',
        'parameters' => ['key' => 'value'],
        'status' => 'pending',
        'progress_percentage' => 0,
        'started_at' => null,
        'completed_at' => null,
        'error_message' => null,
        'result_data' => null,
    ]);

    expect($job->toArray())
        ->toMatchSnapshot();
});
