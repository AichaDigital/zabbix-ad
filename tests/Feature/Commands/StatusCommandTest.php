<?php

use App\Models\AuditLog;
use App\Models\BackgroundJob;
use App\Models\ZabbixConnection;
use App\Models\ZabbixHost;
use App\Models\ZabbixTemplate;

uses()->group('feature', 'commands');

test('status command shows system overview', function () {
    // Create a user first
    $user = \App\Models\User::factory()->create();

    $connections = ZabbixConnection::factory()->count(2)->create();
    ZabbixTemplate::factory()->count(3)->create();
    ZabbixHost::factory()->count(5)->create();
    BackgroundJob::factory()->count(4)->create();

    // Create audit logs with existing connections and user
    foreach ($connections as $connection) {
        AuditLog::factory()->count(3)->create([
            'zabbix_connection_id' => $connection->id,
            'user_id' => $user->id,
        ]);
    }

    $this->artisan('zabbix:maintenance:status')
        ->expectsOutput('🔍 Zabbix Management Platform Status')
        ->expectsOutput('📡 Connections:')
        ->expectsOutput('📋 Templates:')
        ->expectsOutput('🖥️ Hosts:')
        ->expectsOutput('⚙️ Background Jobs:')
        ->expectsOutput('📊 Audit Logs:')
        ->assertExitCode(0);
});

test('status command with detailed flag', function () {
    ZabbixConnection::factory()->count(2)->create();
    ZabbixTemplate::factory()->count(3)->create();
    ZabbixHost::factory()->count(5)->create();

    $this->artisan('zabbix:maintenance:status', ['--detailed' => true])
        ->expectsOutput('📈 Detailed Statistics:')
        ->expectsOutput('  Connections by Environment:')
        ->expectsOutput('  Templates by Type:')
        ->expectsOutput('  Hosts by Status:')
        ->expectsOutput('  Recent Activity (24h):')
        ->assertExitCode(0);
});

test('status command shows error connections', function () {
    ZabbixConnection::factory()->create(['connection_status' => 'error']);

    $this->artisan('zabbix:maintenance:status')
        ->expectsOutput('  ⚠ Connections with errors:')
        ->assertExitCode(0);
});

test('status command shows failed jobs', function () {
    BackgroundJob::factory()->failed()->count(2)->create();

    $this->artisan('zabbix:maintenance:status')
        ->expectsOutput('  ⚠ Failed jobs:')
        ->assertExitCode(0);
});

test('status command output matches expected format', function () {
    ZabbixConnection::factory()->count(2)->create([
        'name' => 'Test Connection 1',
        'environment' => 'local',
        'connection_status' => 'active',
    ]);

    ZabbixConnection::factory()->create([
        'name' => 'Test Connection 2',
        'environment' => 'production',
        'connection_status' => 'error',
    ]);

    ZabbixTemplate::factory()->count(3)->create([
        'template_type' => 'custom',
        'is_optimized' => false,
    ]);

    ZabbixHost::factory()->count(5)->create([
        'status' => 'enabled',
        'available' => 'available',
    ]);

    BackgroundJob::factory()->count(2)->create([
        'status' => 'completed',
    ]);

    BackgroundJob::factory()->create([
        'status' => 'failed',
    ]);

    $this->artisan('zabbix:maintenance:status')
        ->expectsOutput('🔍 Zabbix Management Platform Status')
        ->expectsOutput('📡 Connections:')
        ->expectsOutput('📋 Templates:')
        ->expectsOutput('🖥️ Hosts:')
        ->expectsOutput('⚙️ Background Jobs:')
        ->expectsOutput('📊 Audit Logs:')
        ->assertExitCode(0);
});

test('status command with detailed flag output matches expected format', function () {
    ZabbixConnection::factory()->count(2)->create([
        'environment' => 'local',
    ]);

    ZabbixConnection::factory()->create([
        'environment' => 'production',
    ]);

    ZabbixTemplate::factory()->create([
        'template_type' => 'system',
    ]);

    ZabbixTemplate::factory()->create([
        'template_type' => 'custom',
    ]);

    ZabbixHost::factory()->create([
        'status' => 'enabled',
    ]);

    ZabbixHost::factory()->create([
        'status' => 'disabled',
    ]);

    $this->artisan('zabbix:maintenance:status', ['--detailed' => true])
        ->expectsOutput('🔍 Zabbix Management Platform Status')
        ->expectsOutput('📈 Detailed Statistics:')
        ->expectsOutput('  Connections by Environment:')
        ->expectsOutput('  Templates by Type:')
        ->expectsOutput('  Hosts by Status:')
        ->expectsOutput('  Recent Activity (24h):')
        ->assertExitCode(0);
});
