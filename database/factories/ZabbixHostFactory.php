<?php

namespace Database\Factories;

use App\Models\ZabbixConnection;
use App\Models\ZabbixHost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ZabbixHost>
 */
class ZabbixHostFactory extends Factory
{
    protected $model = ZabbixHost::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'zabbix_connection_id' => ZabbixConnection::factory(),
            'host_id' => 'host-'.uniqid(),
            'host_name' => 'test-host.example.com',
            'visible_name' => 'Test Server',
            'ip_address' => '127.0.0.1',
            'status' => ['enabled', 'disabled', 'maintenance'][array_rand(['enabled', 'disabled', 'maintenance'])],
            'available' => ['unknown', 'available', 'unavailable'][array_rand(['unknown', 'available', 'unavailable'])],
            'templates_count' => rand(0, 10),
            'items_count' => rand(0, 200),
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'enabled',
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'disabled',
        ]);
    }

    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'available' => 'available',
        ]);
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'available' => 'unavailable',
        ]);
    }

    public function healthy(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'enabled',
            'available' => 'available',
        ]);
    }
}
