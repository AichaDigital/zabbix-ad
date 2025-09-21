<?php

namespace App\Jobs\Zabbix;

use App\Models\BackgroundJob;
use App\Models\ZabbixConnection;
use App\Models\ZabbixTemplate;
use App\Services\Zabbix\TemplateManagementService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateTemplateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes

    public int $tries = 3;

    public int $backoff = 30;

    private ZabbixConnection $connection;

    /**
     * @var array<string, mixed>
     */
    private array $templateData;

    private ?ZabbixTemplate $sourceTemplate;

    private BackgroundJob $backgroundJob;

    /**
     * Create a new job instance.
     */
    /**
     * @param  array<string, mixed>  $templateData
     */
    public function __construct(
        ZabbixConnection $connection,
        array $templateData,
        ?ZabbixTemplate $sourceTemplate = null
    ) {
        $this->connection = $connection;
        $this->templateData = $templateData;
        $this->sourceTemplate = $sourceTemplate;
        $queueName = config('zabbix.jobs.queue', 'default');
        $this->onQueue(is_string($queueName) ? $queueName : 'default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->backgroundJob = BackgroundJob::create([
            'job_type' => $this->sourceTemplate ? 'create_template_from_existing' : 'create_template',
            'zabbix_connection_id' => $this->connection->id,
            'parameters' => [
                'connection_name' => $this->connection->name,
                'template_name' => $this->templateData['name'],
                'source_template_id' => $this->sourceTemplate?->template_id,
                'source_template_name' => $this->sourceTemplate?->name,
            ],
            'status' => 'running',
            'progress_percentage' => 0,
            'started_at' => now(),
        ]);

        try {
            Log::info('Starting template creation job', [
                'connection_id' => $this->connection->id,
                'template_name' => $this->templateData['name'],
                'source_template_id' => $this->sourceTemplate?->template_id,
                'job_id' => $this->backgroundJob->id,
            ]);

            $this->updateProgress(10, 'Initializing template management service...');

            $templateService = new TemplateManagementService($this->connection);

            if ($this->sourceTemplate !== null) {
                $this->updateProgress(20, 'Creating template from existing template...');
                $result = $templateService->createTemplateFromExisting($this->sourceTemplate, $this->templateData);
            } else {
                $this->updateProgress(20, 'Creating new template...');
                $result = $templateService->createTemplate($this->templateData);
            }

            $this->updateProgress(100, 'Template creation completed');

            $this->backgroundJob->markAsCompleted([
                'template_id' => $result['template']->template_id,
                'template_name' => $result['template']->name,
                'template_type' => $result['template']->template_type,
                'execution_time_ms' => $result['execution_time_ms'],
                'source_template_id' => $this->sourceTemplate?->template_id,
            ]);

            Log::info('Template creation job completed', [
                'connection_id' => $this->connection->id,
                'template_id' => $result['template']->template_id,
                'template_name' => $result['template']->name,
                'job_id' => $this->backgroundJob->id,
            ]);

        } catch (Exception $e) {
            $this->backgroundJob->markAsFailed($e->getMessage());

            Log::error('Template creation job failed', [
                'connection_id' => $this->connection->id,
                'template_name' => $this->templateData['name'],
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

        Log::error('Template creation job failed permanently', [
            'connection_id' => $this->connection->id,
            'template_name' => $this->templateData['name'],
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
