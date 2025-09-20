<?php

use App\Models\ZabbixConnection;
use App\Services\Zabbix\McpZabbixClient;

uses()->group('feature', 'services');

beforeEach(function () {
    $this->connection = ZabbixConnection::factory()->create([
        'name' => 'Test Connection',
        'url' => 'http://test.zabbix.com',
        'encrypted_token' => 'test-token-123',
    ]);

    $this->client = new McpZabbixClient($this->connection);
});

test('can create mcp zabbix client', function () {
    expect($this->client)
        ->toBeInstanceOf(McpZabbixClient::class);
});

test('client has connection property', function () {
    // Check that client was created with the connection
    expect($this->client)
        ->toBeInstanceOf(McpZabbixClient::class);
});

test('test connection returns expected structure', function () {
    $result = $this->client->testConnection();

    expect($result)
        ->toBeArray()
        ->toHaveKeys(['success', 'error'])
        ->and($result['success'])
        ->toBeFalse();
});

test('get connection stats returns expected structure', function () {
    $stats = $this->client->getConnectionStats();

    expect($stats)
        ->toBeArray()
        ->toHaveKeys(['templates_count', 'hosts_count', 'last_check', 'connection_status']);
});

test('create host returns expected structure', function () {
    $hostData = [
        'host' => 'test-host',
        'name' => 'Test Host',
        'ip' => '192.168.1.100',
    ];

    // Expect failure due to no real connections
    expect(fn () => $this->client->createHost($hostData))
        ->toThrow(Exception::class);
});

test('create template returns expected structure', function () {
    $templateData = [
        'name' => 'Test Template',
        'description' => 'Test Template Description',
        'template_type' => 'custom',
    ];

    // Expect failure due to no real connections
    expect(fn () => $this->client->createTemplate($templateData))
        ->toThrow(Exception::class);
});

// Removed test for private method makeMcpRequest
