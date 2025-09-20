<?php

use App\Models\ZabbixConnection;
use App\Services\Zabbix\ZabbixSyncService;

uses()->group('feature', 'services');

beforeEach(function () {
    $this->connection = ZabbixConnection::factory()->create();
    $this->service = new ZabbixSyncService($this->connection);
});

test('get sync stats returns expected structure', function () {
    $stats = $this->service->getSyncStats();

    expect($stats)->toBeArray()
        ->and($stats)->toHaveKeys(['templates_count', 'hosts_count', 'last_sync', 'connection_status'])
        ->and($stats['templates_count'])->toBeInt()
        ->and($stats['hosts_count'])->toBeInt()
        ->and($stats['connection_status'])->toBeString()
        ->and($stats['templates_count'])->toBe(0)
        ->and($stats['hosts_count'])->toBe(0)
        ->and($stats['last_sync'])->toBeNull();
});

test('sync all throws exception without real connections', function () {
    // Expect failure due to no real connections
    expect(fn () => $this->service->syncAll())
        ->toThrow(Exception::class);
});

test('sync templates throws exception without real connections', function () {
    // Expect failure due to no real connections
    expect(fn () => $this->service->syncTemplates($this->connection->id))
        ->toThrow(Exception::class);
});

test('sync hosts throws exception without real connections', function () {
    // Expect failure due to no real connections
    expect(fn () => $this->service->syncHosts($this->connection->id))
        ->toThrow(Exception::class);
});
