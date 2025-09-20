<?php

use App\Models\ZabbixConnection;
use App\Services\Zabbix\McpZabbixClient;
use App\Services\Zabbix\ZabbixSyncService;

uses()->group('feature', 'services');

it('syncs templates and hosts successfully using a fake MCP client', function () {
    $connection = ZabbixConnection::factory()->create([
        'name' => 'Local Test',
        'url' => 'http://localhost:8080',
        'connection_status' => 'active',
    ]);

    $service = new ZabbixSyncService($connection);

    // Fake client that returns deterministic data
    $fakeClient = new class($connection) extends McpZabbixClient
    {
        public function __construct(private ZabbixConnection $conn)
        {
            parent::__construct($conn);
        }

        public function getTemplates(): array
        {
            return [
                [
                    'templateid' => '10001',
                    'name' => 'Linux System Base',
                    'description' => 'System template',
                    'items_count' => 10,
                    'triggers_count' => 2,
                    'history_retention' => '7d',
                    'trends_retention' => '30d',
                    'is_optimized' => true,
                ],
            ];
        }

        public function getHosts(): array
        {
            return [
                [
                    'hostid' => '20001',
                    'host' => 'srv-01',
                    'name' => 'Server 01',
                    'status' => 0,
                    'available' => 1,
                    'items_count' => 25,
                    'lastcheck' => now()->timestamp,
                    'parentTemplates' => [['templateid' => '10001']],
                    'interfaces' => [['ip' => '192.168.0.10']],
                ],
            ];
        }

        public function getConnectionStats(): array
        {
            return [
                'templates_count' => 1,
                'hosts_count' => 1,
                'last_check' => now(),
                'connection_status' => 'active',
            ];
        }
    };

    $service->setClient($fakeClient);

    $templates = $service->syncTemplates();
    $hosts = $service->syncHosts();

    expect($templates)
        ->toBeArray()
        ->and($templates['synced'])->toBe(1)
        ->and($templates['errors'])->toBe(0);

    expect($hosts)
        ->toBeArray()
        ->and($hosts['synced'])->toBe(1)
        ->and($hosts['errors'])->toBe(0);

    $stats = $service->getSyncStats();
    expect($stats)
        ->toHaveKeys(['templates_count', 'hosts_count', 'last_sync', 'connection_status'])
        ->and($stats['templates_count'])->toBe(1)
        ->and($stats['hosts_count'])->toBe(1)
        ->and($stats['connection_status'])->toBe('active');
});
