<?php

namespace App\Jobs\Zabbix;

use App\Models\BackgroundJob;
use App\Models\ZabbixConnection;
use App\Services\Zabbix\ZabbixSyncService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncZabbixDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes

    public int $tries = 3;

    public int $backoff = 60;

    private ZabbixConnection $zabbixConnection;

    private BackgroundJob $backgroundJob;

    /**
     * Create a new job instance.
     */
    public function __construct(ZabbixConnection $zabbixConnection)
    {
        $this->zabbixConnection = $zabbixConnection;
        $this->onQueue((string) config('zabbix.jobs.queue', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->backgroundJob = BackgroundJob::create([
            'job_type' => 'sync_zabbix_data',
            'zabbix_connection_id' => $this->zabbixConnection->id,
            'parameters' => [
                'connection_name' => $this->zabbixConnection->name,
                'connection_url' => $this->zabbixConnection->url,
            ],
            'status' => 'running',
            'progress_percentage' => 0,
            'started_at' => now(),
        ]);

        try {
            Log::info('Starting Zabbix data sync job', [
                'connection_id' => $this->zabbixConnection->id,
                'connection_name' => $this->zabbixConnection->name,
                'job_id' => $this->backgroundJob->id,
            ]);

            $this->updateProgress(10, 'Initializing sync service...');

            $syncService = app(ZabbixSyncService::class, ['connection' => $this->zabbixConnection]);

            $this->updateProgress(20, 'Testing connection...');
            $connectionTest = $syncService->getSyncStats();

            if ($connectionTest['connection_status'] === 'error') {
                throw new Exception('Connection test failed: '.($connectionTest['error'] ?? 'Unknown error'));
            }

            $this->updateProgress(30, 'Syncing templates...');
            $templateResult = $syncService->syncTemplates();

            $this->updateProgress(60, 'Syncing hosts...');
            $hostResult = $syncService->syncHosts();

            $this->updateProgress(90, 'Updating connection stats...');
            // Connection stats are updated automatically during sync

            $this->updateProgress(100, 'Sync completed successfully');

            $this->backgroundJob->markAsCompleted([
                'templates_synced' => $templateResult['synced'],
                'templates_errors' => $templateResult['errors'],
                'hosts_synced' => $hostResult['synced'],
                'hosts_errors' => $hostResult['errors'],
                'connection_status' => $connectionTest['connection_status'],
            ]);

            Log::info('Zabbix data sync job completed successfully', [
                'connection_id' => $this->zabbixConnection->id,
                'job_id' => $this->backgroundJob->id,
                'results' => [
                    'templates_synced' => $templateResult['synced'],
                    'hosts_synced' => $hostResult['synced'],
                ],
            ]);

        } catch (Exception $e) {
            $this->backgroundJob->markAsFailed($e->getMessage());

            Log::error('Zabbix data sync job failed', [
                'connection_id' => $this->zabbixConnection->id,
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

        Log::error('Zabbix data sync job failed permanently', [
            'connection_id' => $this->zabbixConnection->id,
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
