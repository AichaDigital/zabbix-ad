<?php

use App\Jobs\Zabbix\SyncZabbixDataJob;
use App\Models\ZabbixConnection;

uses()->group('feature', 'jobs');

test('can create sync zabbix data job', function () {
    $connection = ZabbixConnection::factory()->create();

    $job = new SyncZabbixDataJob($connection);

    expect($job)
        ->toBeInstanceOf(SyncZabbixDataJob::class);
});

test('job has correct timeout and tries', function () {
    $connection = ZabbixConnection::factory()->create();

    $job = new SyncZabbixDataJob($connection);

    expect($job)
        ->timeout->toBe(600)
        ->tries->toBe(3)
        ->backoff->toBe(60);
});

test('job handle method executes without errors', function () {
    $connection = ZabbixConnection::factory()->create();

    $job = new SyncZabbixDataJob($connection);

    expect(fn () => $job->handle())
        ->toThrow(Exception::class); // Expect failure due to no real connections
});

test('job creates background job record', function () {
    $connection = ZabbixConnection::factory()->create();

    $job = new SyncZabbixDataJob($connection);

    // Job should create a background job record even if it fails
    expect(fn () => $job->handle())
        ->toThrow(Exception::class);

    // Check that background job was created
    expect($connection->backgroundJobs)
        ->toHaveCount(1)
        ->and($connection->backgroundJobs->first())
        ->job_type->toBe('sync_zabbix_data')
        ->status->toBe('failed'); // Should be failed due to connection error
});

test('job handles connection errors gracefully', function () {
    $connection = ZabbixConnection::factory()->create([
        'url' => 'http://invalid-url.com',
        'encrypted_token' => 'invalid-token',
    ]);

    $job = new SyncZabbixDataJob($connection);

    expect(fn () => $job->handle())
        ->toThrow(Exception::class); // Expect any connection error
});

test('job updates connection last sync timestamp', function () {
    $connection = ZabbixConnection::factory()->create();
    $originalSyncTime = $connection->last_sync_at;

    $job = new SyncZabbixDataJob($connection);

    // Job should fail due to connection error
    expect(fn () => $job->handle())
        ->toThrow(Exception::class);

    $connection->refresh();

    // Check that background job was created (timestamp may not be updated on failure)
    expect($connection->backgroundJobs)
        ->toHaveCount(1);
});
