<?php

use App\Models\AuditLog;
use App\Models\ZabbixConnection;

uses()->group('unit', 'models');

test('can create audit log', function () {
    $user = \App\Models\User::factory()->create();
    $connection = ZabbixConnection::factory()->create();
    $log = AuditLog::factory()->create([
        'zabbix_connection_id' => $connection->id,
        'user_id' => $user->id,
        'action' => 'test_action',
        'resource_type' => 'connection',
        'status' => 'success',
    ]);

    expect($log)
        ->toBeInstanceOf(AuditLog::class)
        ->user_id->toBe($user->id)
        ->action->toBe('test_action')
        ->resource_type->toBe('connection')
        ->status->toBe('success')
        ->zabbix_connection_id->toBe($connection->id);
});

test('audit log belongs to connection', function () {
    $connection = ZabbixConnection::factory()->create();
    $log = AuditLog::factory()->create([
        'zabbix_connection_id' => $connection->id,
    ]);

    $log->load('zabbixConnection');

    expect($log->zabbixConnection)
        ->toBeInstanceOf(ZabbixConnection::class)
        ->id->toBe($connection->id);
});

test('audit log has default values', function () {
    $log = AuditLog::factory()->success()->create();

    expect($log)
        ->status->toBe('success')
        ->error_message->toBeNull();
});

test('scope successful returns only successful logs', function () {
    AuditLog::factory()->success()->create();
    AuditLog::factory()->failed()->create();

    $successfulLogs = AuditLog::successful()->get();

    expect($successfulLogs)
        ->toHaveCount(1)
        ->and($successfulLogs->first()->status)
        ->toBe('success');
});

test('scope failed returns only failed logs', function () {
    AuditLog::factory()->success()->create();
    AuditLog::factory()->failed()->create();

    $failedLogs = AuditLog::failed()->get();

    expect($failedLogs)
        ->toHaveCount(1)
        ->and($failedLogs->first()->status)
        ->toBe('failed');
});

test('scope by action returns logs by action', function () {
    AuditLog::factory()->create(['action' => 'create_host']);
    AuditLog::factory()->create(['action' => 'update_template']);

    $createLogs = AuditLog::byAction('create_host')->get();
    $updateLogs = AuditLog::byAction('update_template')->get();

    expect($createLogs)
        ->toHaveCount(1)
        ->and($createLogs->first()->action)
        ->toBe('create_host')
        ->and($updateLogs)
        ->toHaveCount(1)
        ->and($updateLogs->first()->action)
        ->toBe('update_template');
});

test('scope by resource type returns logs by resource type', function () {
    AuditLog::factory()->create(['resource_type' => 'host']);
    AuditLog::factory()->create(['resource_type' => 'template']);

    $hostLogs = AuditLog::byResourceType('host')->get();
    $templateLogs = AuditLog::byResourceType('template')->get();

    expect($hostLogs)
        ->toHaveCount(1)
        ->and($hostLogs->first()->resource_type)
        ->toBe('host')
        ->and($templateLogs)
        ->toHaveCount(1)
        ->and($templateLogs->first()->resource_type)
        ->toBe('template');
});

test('scope recent returns recent logs', function () {
    AuditLog::factory()->create(['created_at' => now()->subDays(2)]);
    AuditLog::factory()->create(['created_at' => now()->subHours(1)]);

    $recentLogs = AuditLog::recent(1)->get();

    expect($recentLogs)
        ->toHaveCount(1);
});

test('audit log model structure matches expected schema', function () {
    $log = AuditLog::factory()->make([
        'user_id' => 1,
        'action' => 'test_action',
        'resource_type' => 'connection',
        'resource_id' => 'test-id-123',
        'old_values' => ['name' => 'Old Name'],
        'new_values' => ['name' => 'New Name'],
        'status' => 'success',
        'execution_time_ms' => 100,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test Agent',
        'created_at' => '2025-01-01 00:00:00',
    ]);

    expect($log->toArray())
        ->toMatchSnapshot();
});
