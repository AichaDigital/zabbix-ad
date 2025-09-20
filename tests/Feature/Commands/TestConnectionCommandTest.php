<?php

use App\Models\ZabbixConnection;

uses()->group('feature', 'commands');

test('test connection command with no connections', function () {
    $this->artisan('zabbix:connection:test', ['--no-interaction' => true])
        ->expectsOutput('No connections found.')
        ->assertExitCode(1);
});

test('test connection command with connections', function () {
    // Create connections but don't test them individually
    ZabbixConnection::factory()->active()->count(2)->create();

    $this->artisan('zabbix:connection:test', ['--all' => true])
        ->expectsOutput('Testing all Zabbix connections...')
        ->assertExitCode(1); // Expect failure due to no real connections
});

test('test connection command with specific connection', function () {
    $connection = ZabbixConnection::factory()->active()->create();

    $this->artisan('zabbix:connection:test', ['connection' => $connection->name])
        ->expectsOutput('Testing connection: '.$connection->name)
        ->assertExitCode(1); // Expect failure due to no real connections
});

test('test connection command with all flag', function () {
    ZabbixConnection::factory()->count(3)->create();

    $this->artisan('zabbix:connection:test', ['--all' => true])
        ->expectsOutput('Testing all Zabbix connections...')
        ->assertExitCode(1); // Expect failure due to no real connections
});

test('test connection command output matches expected format', function () {
    $connection = ZabbixConnection::factory()->active()->create([
        'name' => 'Test Connection',
        'url' => 'http://test.zabbix.com',
        'environment' => 'local',
    ]);

    $this->artisan('zabbix:connection:test', ['connection' => $connection->name])
        ->expectsOutput('Testing connection: '.$connection->name)
        ->assertExitCode(1); // Expect failure due to no real connections
});

test('test all connections command output matches expected format', function () {
    ZabbixConnection::factory()->count(2)->create([
        'name' => 'Test Connection 1',
        'url' => 'http://test1.zabbix.com',
        'environment' => 'local',
    ]);

    ZabbixConnection::factory()->create([
        'name' => 'Test Connection 2',
        'url' => 'http://test2.zabbix.com',
        'environment' => 'production',
    ]);

    $this->artisan('zabbix:connection:test', ['--all' => true])
        ->expectsOutput('Testing all Zabbix connections...')
        ->assertExitCode(1); // Expect failure due to no real connections
});
