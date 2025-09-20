<?php

namespace Database\Factories;

use App\Models\ZabbixConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ZabbixConnection>
 */
class ZabbixConnectionFactory extends Factory
{
    protected $model = ZabbixConnection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Test Zabbix Connection',
            'description' => 'Test connection description',
            'url' => 'http://localhost:8080',
            'encrypted_token' => 'test-token-'.uniqid(),
            'environment' => ['local', 'production', 'staging'][array_rand(['local', 'production', 'staging'])],
            'is_active' => true,
            'max_requests_per_minute' => 60,
            'timeout_seconds' => 30,
            'connection_status' => ['active', 'inactive', 'error'][array_rand(['active', 'inactive', 'error'])],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'connection_status' => 'active',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'connection_status' => 'inactive',
        ]);
    }

    public function local(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => 'local',
            'url' => 'http://localhost:8080',
        ]);
    }

    public function production(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => 'production',
        ]);
    }
}
