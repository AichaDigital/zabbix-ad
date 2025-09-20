<?php

namespace App\Jobs\Zabbix;

use App\Models\BackgroundJob;
use App\Models\ZabbixConnection;
use App\Services\Zabbix\HostManagementService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateHostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes

    public int $tries = 3;

    public int $backoff = 30;

    private ZabbixConnection $connection;

    /**
     * @var list<array<string, mixed>>
     */
    private array $hostsData;

    private BackgroundJob $backgroundJob;

    /**
     * Create a new job instance.
     */
    /**
     * @param  list<array<string, mixed>>  $hostsData
     */
    public function __construct(ZabbixConnection $connection, array $hostsData)
    {
        $this->connection = $connection;
        $this->hostsData = $hostsData;
        $queueName = config('zabbix.jobs.queue', 'default');
        $this->onQueue(is_string($queueName) ? $queueName : 'default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->backgroundJob = BackgroundJob::create([
            'job_type' => 'create_hosts_batch',
            'zabbix_connection_id' => $this->connection->id,
            'parameters' => [
                'connection_name' => $this->connection->name,
                'hosts_count' => count($this->hostsData),
                'host_names' => array_column($this->hostsData, 'host'),
            ],
            'status' => 'running',
            'progress_percentage' => 0,
            'started_at' => now(),
        ]);

        try {
            Log::info('Starting batch host creation job', [
                'connection_id' => $this->connection->id,
                'hosts_count' => count($this->hostsData),
                'job_id' => $this->backgroundJob->id,
            ]);

            $this->updateProgress(10, 'Initializing host management service...');

            $hostService = new HostManagementService($this->connection);

            $this->updateProgress(20, 'Creating hosts...');

            $result = $hostService->createHostsBatch($this->hostsData);

            $this->updateProgress(100, 'Host creation completed');

            $this->backgroundJob->markAsCompleted([
                'total_hosts' => count($this->hostsData),
                'created' => $result['results']['created'],
                'errors' => $result['results']['errors'],
                'execution_time_ms' => $result['execution_time_ms'],
                'hosts' => $result['results']['hosts'],
            ]);

            Log::info('Batch host creation job completed', [
                'connection_id' => $this->connection->id,
                'created' => $result['results']['created'],
                'errors' => $result['results']['errors'],
                'job_id' => $this->backgroundJob->id,
            ]);

        } catch (Exception $e) {
            $this->backgroundJob->markAsFailed($e->getMessage());

            Log::error('Batch host creation job failed', [
                'connection_id' => $this->connection->id,
                'job_id' => $this->backgroundJob->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        if (isset($this->backgroundJob)) {
            $this->backgroundJob->markAsFailed($exception->getMessage());
        }

        Log::error('Batch host creation job failed permanently', [
            'connection_id' => $this->connection->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Update job progress
     */
    private function updateProgress(int $percentage, string $message): void
    {
        $this->backgroundJob->updateProgress($percentage, ['message' => $message]);
    }
}
