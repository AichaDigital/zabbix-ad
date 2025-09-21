<?php

namespace App\Jobs\Zabbix;

use App\Models\BackgroundJob;
use App\Models\ZabbixConnection;
use App\Models\ZabbixTemplate;
use App\Services\Zabbix\TemplateOptimizationService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OptimizeTemplatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900; // 15 minutes

    public int $tries = 3;

    public int $backoff = 120;

    private ZabbixConnection $connection;

    private ?ZabbixTemplate $template;

    private BackgroundJob $backgroundJob;

    private bool $autoOptimize;

    /**
     * Create a new job instance.
     */
    public function __construct(
        ZabbixConnection $connection,
        ?ZabbixTemplate $template = null,
        bool $autoOptimize = false
    ) {
        $this->connection = $connection;
        $this->template = $template;
        $this->autoOptimize = $autoOptimize;
        $queueName = config('zabbix.jobs.queue', 'default');
        $this->onQueue(is_string($queueName) ? $queueName : 'default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->backgroundJob = BackgroundJob::create([
            'job_type' => $this->template ? 'optimize_single_template' : 'optimize_all_templates',
            'zabbix_connection_id' => $this->connection->id,
            'parameters' => [
                'connection_name' => $this->connection->name,
                'template_id' => $this->template?->template_id,
                'template_name' => $this->template?->name,
                'auto_optimize' => $this->autoOptimize,
            ],
            'status' => 'running',
            'progress_percentage' => 0,
            'started_at' => now(),
        ]);

        try {
            Log::info('Starting template optimization job', [
                'connection_id' => $this->connection->id,
                'template_id' => $this->template?->template_id,
                'auto_optimize' => $this->autoOptimize,
                'job_id' => $this->backgroundJob->id,
            ]);

            $this->updateProgress(10, 'Initializing optimization service...');

            $optimizationService = new TemplateOptimizationService($this->connection);

            if ($this->template) {
                $this->optimizeSingleTemplate($optimizationService);
            } else {
                $this->optimizeAllTemplates($optimizationService);
            }

        } catch (Exception $e) {
            $this->backgroundJob->markAsFailed($e->getMessage());

            Log::error('Template optimization job failed', [
                'connection_id' => $this->connection->id,
                'template_id' => $this->template?->template_id,
                'job_id' => $this->backgroundJob->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Optimize a single template
     */
    private function optimizeSingleTemplate(TemplateOptimizationService $service): void
    {
        if (! $this->template) {
            throw new Exception('Template is required for single template optimization');
        }

        $this->updateProgress(20, 'Analyzing template...');

        if ($this->template === null) {
            throw new \Exception('Template is required for optimization');
        }

        $analysis = $service->analyzeTemplate($this->template);

        $this->updateProgress(40, 'Applying optimization...');

        $result = $service->optimizeTemplate($this->template);

        $this->updateProgress(100, 'Template optimization completed');

        $this->backgroundJob->markAsCompleted([
            'template_id' => $this->template->template_id,
            'template_name' => $this->template->name,
            'optimization_settings' => $result['optimization_settings'],
            'potential_savings' => $analysis['potential_savings'],
            'execution_time_ms' => $result['execution_time_ms'],
        ]);

        Log::info('Single template optimization completed', [
            'connection_id' => $this->connection->id,
            'template_id' => $this->template->template_id,
            'template_name' => $this->template->name,
            'job_id' => $this->backgroundJob->id,
        ]);
    }

    /**
     * Optimize all templates
     */
    private function optimizeAllTemplates(TemplateOptimizationService $service): void
    {
        if ($this->autoOptimize) {
            $this->updateProgress(20, 'Running auto optimization...');

            $result = $service->autoOptimizeAllTemplates();

            $this->updateProgress(100, 'Auto optimization completed');

            $this->backgroundJob->markAsCompleted([
                'optimization_type' => 'auto',
                'result' => $result['result'],
                'execution_time_ms' => $result['execution_time_ms'],
            ]);

            Log::info('Auto template optimization completed', [
                'connection_id' => $this->connection->id,
                'job_id' => $this->backgroundJob->id,
            ]);
        } else {
            $this->updateProgress(20, 'Getting templates needing optimization...');

            $templates = $this->connection->templates()
                ->needsOptimization()
                ->get();

            $totalTemplates = $templates->count();
            $optimized = 0;
            $errors = 0;

            foreach ($templates as $index => $template) {
                try {
                    $this->updateProgress(
                        20 + (($index / $totalTemplates) * 70),
                        "Optimizing template: {$template->name}"
                    );

                    $service->optimizeTemplate($template);
                    $optimized++;

                } catch (Exception $e) {
                    $errors++;
                    Log::warning('Failed to optimize template in batch job', [
                        'connection_id' => $this->connection->id,
                        'template_id' => $template->template_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->updateProgress(100, 'Batch optimization completed');

            $this->backgroundJob->markAsCompleted([
                'optimization_type' => 'batch',
                'total_templates' => $totalTemplates,
                'optimized' => $optimized,
                'errors' => $errors,
            ]);

            Log::info('Batch template optimization completed', [
                'connection_id' => $this->connection->id,
                'total_templates' => $totalTemplates,
                'optimized' => $optimized,
                'errors' => $errors,
                'job_id' => $this->backgroundJob->id,
            ]);
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

        Log::error('Template optimization job failed permanently', [
            'connection_id' => $this->connection->id,
            'template_id' => $this->template?->template_id,
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
