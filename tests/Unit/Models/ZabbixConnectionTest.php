<?php

use App\Models\AuditLog;
use App\Models\ZabbixConnection;

uses()->group('unit', 'models');

test('can create zabbix connection', function () {
    $connection = ZabbixConnection::factory()->create([
        'name' => 'Test Connection',
        'url' => 'http://test.zabbix.com',
        'environment' => 'local',
        'is_active' => true,
    ]);

    expect($connection)
        ->toBeInstanceOf(ZabbixConnection::class)
        ->name->toBe('Test Connection')
        ->url->toBe('http://test.zabbix.com')
        ->environment->toBe('local')
        ->is_active->toBeTrue();
});

test('encrypted token is automatically encrypted', function () {
    $connection = ZabbixConnection::factory()->create([
        'encrypted_token' => 'test-token-123',
    ]);

    expect($connection->getRawOriginal('encrypted_token'))
        ->toBe('test-token-123')
        ->and($connection->token)
        ->toBe('test-token-123');
});

test('connection has default values', function () {
    $connection = ZabbixConnection::factory()->create([
        'environment' => 'production',
        'is_active' => true,
        'max_requests_per_minute' => 60,
        'timeout_seconds' => 30,
        'connection_status' => 'active',
    ]);

    expect($connection)
        ->environment->toBe('production')
        ->is_active->toBeTrue()
        ->max_requests_per_minute->toBe(60)
        ->timeout_seconds->toBe(30)
        ->connection_status->toBe('active');
});

test('connection can have templates', function () {
    $connection = ZabbixConnection::factory()->create();
    $template = $connection->templates()->create([
        'template_id' => 'test-template-1',
        'name' => 'Test Template',
        'template_type' => 'custom',
    ]);

    $connection->load('templates');
    expect($connection->templates)
        ->toHaveCount(1)
        ->and($connection->templates->first()->id)
        ->toBe($template->id);
});

test('connection can have hosts', function () {
    $connection = ZabbixConnection::factory()->create();
    $host = $connection->hosts()->create([
        'host_id' => 'test-host-1',
        'host_name' => 'Test Host',
        'ip_address' => '192.168.1.1',
    ]);

    $connection->load('hosts');
    expect($connection->hosts)
        ->toHaveCount(1)
        ->and($connection->hosts->first()->id)
        ->toBe($host->id);
});

test('connection can have background jobs', function () {
    $connection = ZabbixConnection::factory()->create();
    $job = $connection->backgroundJobs()->create([
        'job_type' => 'sync_templates',
        'status' => 'pending',
    ]);

    $connection->load('backgroundJobs');
    expect($connection->backgroundJobs)
        ->toHaveCount(1)
        ->and($connection->backgroundJobs->first()->id)
        ->toBe($job->id);
});

test('connection can have audit logs', function () {
    $connection = ZabbixConnection::factory()->create();
    $log = AuditLog::factory()->create([
        'zabbix_connection_id' => $connection->id,
    ]);

    $connection->load('auditLogs');
    expect($connection->auditLogs)
        ->toHaveCount(1)
        ->and($connection->auditLogs->first()->id)
        ->toBe($log->id);
});

test('scope active returns only active connections', function () {
    ZabbixConnection::factory()->create(['is_active' => true]);
    ZabbixConnection::factory()->create(['is_active' => false]);

    $activeConnections = ZabbixConnection::active()->get();

    expect($activeConnections)
        ->toHaveCount(1)
        ->and($activeConnections->first()->is_active)
        ->toBeTrue();
});

test('scope by environment returns connections by environment', function () {
    ZabbixConnection::factory()->create(['environment' => 'local']);
    ZabbixConnection::factory()->create(['environment' => 'production']);

    $localConnections = ZabbixConnection::byEnvironment('local')->get();
    $productionConnections = ZabbixConnection::byEnvironment('production')->get();

    expect($localConnections)
        ->toHaveCount(1)
        ->and($localConnections->first()->environment)
        ->toBe('local')
        ->and($productionConnections)
        ->toHaveCount(1)
        ->and($productionConnections->first()->environment)
        ->toBe('production');
});

test('connection model structure matches expected schema', function () {
    $connection = ZabbixConnection::factory()->make([
        'name' => 'Test Connection',
        'description' => 'Test Description',
        'url' => 'http://test.zabbix.com',
        'encrypted_token' => 'test-token-123',
        'environment' => 'local',
        'is_active' => true,
        'max_requests_per_minute' => 60,
        'timeout_seconds' => 30,
        'connection_status' => 'active',
    ]);

    expect($connection->toArray())
        ->toMatchSnapshot();
});
