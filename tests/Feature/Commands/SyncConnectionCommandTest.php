<?php

use App\Models\ZabbixConnection;

uses()->group('feature', 'commands');

test('sync connection command with no connections', function () {
    $this->artisan('zabbix:connection:sync')
        ->expectsOutput('No connections found.')
        ->assertExitCode(1);
});

test('sync connection command with connections', function () {
    $connection = ZabbixConnection::factory()->active()->create([
        'name' => 'Test Connection',
    ]);

    $this->artisan('zabbix:connection:sync', ['connection' => $connection->name])
        ->expectsOutput('Syncing connection: '.$connection->name)
        ->assertExitCode(1); // Expect failure due to no real connections
});

test('sync connection command with all flag', function () {
    ZabbixConnection::factory()->active()->count(2)->create();

    $this->artisan('zabbix:connection:sync', ['--all' => true])
        ->expectsOutput('Syncing all Zabbix connections...')
        ->assertExitCode(1); // Expect failure due to no real connections
});

test('sync connection command with queue flag', function () {
    $connection = ZabbixConnection::factory()->active()->create([
        'name' => 'Test Connection',
    ]);

    $this->artisan('zabbix:connection:sync', [
        'connection' => $connection->name,
        '--queue' => true,
    ])
        ->expectsOutput('Queuing sync for connection: '.$connection->name)
        ->assertExitCode(0); // Job is queued but not executed
});

test('sync connection command output matches expected format', function () {
    $connection = ZabbixConnection::factory()->active()->create([
        'name' => 'Test Connection',
        'url' => 'http://test.zabbix.com',
    ]);

    $this->artisan('zabbix:connection:sync', ['connection' => $connection->name])
        ->expectsOutput('Syncing connection: '.$connection->name)
        ->assertExitCode(1); // Expect failure due to no real connections
});

test('sync all connections command output matches expected format', function () {
    ZabbixConnection::factory()->active()->count(2)->create([
        'name' => 'Test Connection 1',
    ]);

    ZabbixConnection::factory()->active()->create([
        'name' => 'Test Connection 2',
    ]);

    $this->artisan('zabbix:connection:sync', ['--all' => true])
        ->expectsOutput('Syncing all Zabbix connections...')
        ->assertExitCode(1); // Expect failure due to no real connections
});
