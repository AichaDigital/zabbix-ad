<?php

namespace Database\Factories;

use App\Models\ZabbixConnection;
use App\Models\ZabbixTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ZabbixTemplate>
 */
class ZabbixTemplateFactory extends Factory
{
    protected $model = ZabbixTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'zabbix_connection_id' => ZabbixConnection::factory(),
            'template_id' => 'template-'.uniqid(),
            'name' => 'Test Template',
            'description' => 'Test template description',
            'template_type' => ['system', 'custom', 'imported'][array_rand(['system', 'custom', 'imported'])],
            'items_count' => rand(0, 100),
            'triggers_count' => rand(0, 50),
            'history_retention' => ['7d', '30d', '90d', '365d'][array_rand(['7d', '30d', '90d', '365d'])],
            'trends_retention' => ['30d', '90d', '365d', '730d'][array_rand(['30d', '90d', '365d', '730d'])],
            'is_optimized' => rand(0, 100) < 30,
        ];
    }

    public function optimized(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_optimized' => true,
            'history_retention' => '7d',
            'trends_retention' => '30d',
        ]);
    }

    public function needsOptimization(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_optimized' => false,
            'history_retention' => '365d',
            'trends_retention' => '730d',
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'template_type' => 'system',
        ]);
    }

    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'template_type' => 'custom',
        ]);
    }
}
