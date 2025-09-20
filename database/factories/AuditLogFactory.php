<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\ZabbixConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'zabbix_connection_id' => ZabbixConnection::factory(),
            'action' => ['create_connection', 'update_connection', 'delete_connection', 'sync_templates', 'sync_hosts', 'optimize_template', 'create_host', 'create_template'][array_rand(['create_connection', 'update_connection', 'delete_connection', 'sync_templates', 'sync_hosts', 'optimize_template', 'create_host', 'create_template'])],
            'resource_type' => ['connection', 'template', 'host', 'job'][array_rand(['connection', 'template', 'host', 'job'])],
            'resource_id' => uniqid(),
            'old_values' => ['name' => 'Old Name'],
            'new_values' => ['name' => 'New Name'],
            'status' => ['success', 'failed', 'partial'][array_rand(['success', 'failed', 'partial'])],
            'execution_time_ms' => rand(100, 5000),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test User Agent',
            'created_at' => now(),
        ];
    }

    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'error_message' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => 'Operation failed due to connection timeout',
        ]);
    }

    public function partial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'partial',
            'error_message' => 'Some operations completed successfully',
        ]);
    }
}
