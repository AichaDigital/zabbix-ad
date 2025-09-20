<?php

namespace Database\Factories;

use App\Models\BackgroundJob;
use App\Models\ZabbixConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BackgroundJob>
 */
class BackgroundJobFactory extends Factory
{
    protected $model = BackgroundJob::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_type' => ['sync_templates', 'sync_hosts', 'optimize_templates', 'create_hosts', 'create_template', 'cleanup_old_jobs'][array_rand(['sync_templates', 'sync_hosts', 'optimize_templates', 'create_hosts', 'create_template', 'cleanup_old_jobs'])],
            'zabbix_connection_id' => ZabbixConnection::factory(),
            'parameters' => [
                'template_ids' => [uniqid()],
                'force' => true,
            ],
            'status' => ['pending', 'running', 'completed', 'failed', 'cancelled'][array_rand(['pending', 'running', 'completed', 'failed', 'cancelled'])],
            'progress_percentage' => rand(0, 100),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'progress_percentage' => 0,
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'progress_percentage' => rand(1, 99),
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'progress_percentage' => 100,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'result_data' => ['message' => 'Job completed successfully'],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'error_message' => 'Job failed due to connection timeout',
        ]);
    }
}
