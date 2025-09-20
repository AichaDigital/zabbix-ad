<?php

use App\Models\ZabbixConnection;
use App\Models\ZabbixHost;

uses()->group('unit', 'models');

test('can create zabbix host', function () {
    $connection = ZabbixConnection::factory()->create();
    $host = ZabbixHost::factory()->create([
        'zabbix_connection_id' => $connection->id,
        'host_id' => 'test-host-1',
        'host_name' => 'Test Host',
        'ip_address' => '192.168.1.1',
    ]);

    expect($host)
        ->toBeInstanceOf(ZabbixHost::class)
        ->host_id->toBe('test-host-1')
        ->host_name->toBe('Test Host')
        ->ip_address->toBe('192.168.1.1')
        ->zabbix_connection_id->toBe($connection->id);
});

test('host belongs to connection', function () {
    $connection = ZabbixConnection::factory()->create();
    $host = ZabbixHost::factory()->create([
        'zabbix_connection_id' => $connection->id,
    ]);

    $host->load('connection');

    expect($host->connection)
        ->toBeInstanceOf(ZabbixConnection::class)
        ->id->toBe($connection->id);
});

test('host has default values', function () {
    $host = ZabbixHost::factory()->make([
        'status' => 'enabled',
        'available' => 'unknown',
        'templates_count' => 0,
        'items_count' => 0,
    ]);

    expect($host)
        ->status->toBe('enabled')
        ->available->toBe('unknown')
        ->templates_count->toBe(0)
        ->items_count->toBe(0);
});

test('scope enabled returns only enabled hosts', function () {
    ZabbixHost::factory()->create(['status' => 'enabled']);
    ZabbixHost::factory()->create(['status' => 'disabled']);

    $enabledHosts = ZabbixHost::enabled()->get();

    expect($enabledHosts)
        ->toHaveCount(1)
        ->and($enabledHosts->first()->status)
        ->toBe('enabled');
});

test('scope available returns hosts by availability', function () {
    ZabbixHost::factory()->create(['available' => 'available']);
    ZabbixHost::factory()->create(['available' => 'unavailable']);

    $availableHosts = ZabbixHost::available()->get();

    expect($availableHosts)
        ->toHaveCount(1)
        ->and($availableHosts->first()->available)
        ->toBe('available');
});

test('scope by status returns hosts by status', function () {
    ZabbixHost::factory()->create(['status' => 'enabled']);
    ZabbixHost::factory()->create(['status' => 'disabled']);

    $enabledHosts = ZabbixHost::byStatus('enabled')->get();
    $disabledHosts = ZabbixHost::byStatus('disabled')->get();

    expect($enabledHosts)
        ->toHaveCount(1)
        ->and($enabledHosts->first()->status)
        ->toBe('enabled')
        ->and($disabledHosts)
        ->toHaveCount(1)
        ->and($disabledHosts->first()->status)
        ->toBe('disabled');
});

test('scope by availability returns hosts by availability', function () {
    ZabbixHost::factory()->create(['available' => 'available']);
    ZabbixHost::factory()->create(['available' => 'unavailable']);

    $availableHosts = ZabbixHost::byAvailability('available')->get();
    $unavailableHosts = ZabbixHost::byAvailability('unavailable')->get();

    expect($availableHosts)
        ->toHaveCount(1)
        ->and($availableHosts->first()->available)
        ->toBe('available')
        ->and($unavailableHosts)
        ->toHaveCount(1)
        ->and($unavailableHosts->first()->available)
        ->toBe('unavailable');
});

test('host model structure matches expected schema', function () {
    $host = ZabbixHost::factory()->make([
        'host_id' => 'host-123',
        'host_name' => 'Test Host',
        'visible_name' => 'Test Host Visible',
        'ip_address' => '192.168.1.1',
        'status' => 'enabled',
        'available' => 'available',
        'templates_count' => 5,
        'items_count' => 100,
        'last_check' => '2025-01-01 00:00:00',
        'last_sync' => '2025-01-01 00:00:00',
    ]);

    expect($host->toArray())
        ->toMatchSnapshot();
});
