<?php

namespace Tests;

use App\Models\AuditLog;
use App\Models\BackgroundJob;
use App\Models\ZabbixConnection;
use App\Models\ZabbixHost;
use App\Models\ZabbixTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class ZabbixTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up common test data
        $this->setUpTestData();
    }

    protected function setUpTestData(): void
    {
        // Create test connections
        $this->localConnection = ZabbixConnection::factory()->create([
            'name' => 'Local Zabbix',
            'url' => 'http://localhost:8080',
            'environment' => 'local',
            'is_active' => true,
        ]);

        $this->productionConnection = ZabbixConnection::factory()->create([
            'name' => 'Production Zabbix',
            'url' => 'https://zabbix.company.com',
            'environment' => 'production',
            'is_active' => true,
        ]);

        // Create test templates
        $this->systemTemplate = ZabbixTemplate::factory()->create([
            'zabbix_connection_id' => $this->localConnection->id,
            'template_type' => 'system',
            'name' => 'Linux Server',
            'is_optimized' => false,
        ]);

        $this->customTemplate = ZabbixTemplate::factory()->create([
            'zabbix_connection_id' => $this->localConnection->id,
            'template_type' => 'custom',
            'name' => 'Custom Application',
            'is_optimized' => true,
        ]);

        // Create test hosts
        $this->enabledHost = ZabbixHost::factory()->create([
            'zabbix_connection_id' => $this->localConnection->id,
            'status' => 'enabled',
            'available' => 'available',
        ]);

        $this->disabledHost = ZabbixHost::factory()->create([
            'zabbix_connection_id' => $this->localConnection->id,
            'status' => 'disabled',
            'available' => 'unavailable',
        ]);

        // Create test background jobs
        $this->completedJob = BackgroundJob::factory()->create([
            'zabbix_connection_id' => $this->localConnection->id,
            'status' => 'completed',
        ]);

        $this->failedJob = BackgroundJob::factory()->create([
            'zabbix_connection_id' => $this->localConnection->id,
            'status' => 'failed',
        ]);

        // Create test audit logs
        $this->successLog = AuditLog::factory()->create([
            'zabbix_connection_id' => $this->localConnection->id,
            'status' => 'success',
        ]);

        $this->errorLog = AuditLog::factory()->create([
            'zabbix_connection_id' => $this->localConnection->id,
            'status' => 'error',
        ]);
    }

    protected function createTestConnection(array $attributes = []): ZabbixConnection
    {
        return ZabbixConnection::factory()->create(array_merge([
            'name' => 'Test Connection',
            'url' => 'http://test.zabbix.com',
            'environment' => 'test',
            'is_active' => true,
        ], $attributes));
    }

    protected function createTestTemplate(array $attributes = []): ZabbixTemplate
    {
        return ZabbixTemplate::factory()->create(array_merge([
            'zabbix_connection_id' => $this->localConnection->id,
            'template_type' => 'custom',
            'name' => 'Test Template',
            'is_optimized' => false,
        ], $attributes));
    }

    protected function createTestHost(array $attributes = []): ZabbixHost
    {
        return ZabbixHost::factory()->create(array_merge([
            'zabbix_connection_id' => $this->localConnection->id,
            'status' => 'enabled',
            'available' => 'available',
        ], $attributes));
    }
}
