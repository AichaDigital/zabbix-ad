<?php

namespace App\Jobs\Zabbix;

use App\Models\AuditLog;
use App\Models\BackgroundJob;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupOldJobsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes

    public int $tries = 1; // Don't retry cleanup jobs

    private int $daysToKeep;

    /**
     * Create a new job instance.
     */
    public function __construct(int $daysToKeep = 30)
    {
        $this->daysToKeep = $daysToKeep;
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting cleanup of old jobs and audit logs', [
                'days_to_keep' => $this->daysToKeep,
            ]);

            $cutoffDate = now()->subDays($this->daysToKeep);

            // Cleanup old background jobs
            $deletedJobs = BackgroundJob::where('created_at', '<', $cutoffDate)
                ->whereIn('status', ['completed', 'failed', 'cancelled'])
                ->delete();

            // Cleanup old audit logs
            $deletedAuditLogs = AuditLog::where('created_at', '<', $cutoffDate)
                ->delete();

            Log::info('Cleanup completed successfully', [
                'deleted_jobs' => $deletedJobs,
                'deleted_audit_logs' => $deletedAuditLogs,
                'cutoff_date' => $cutoffDate,
            ]);

        } catch (Exception $e) {
            Log::error('Cleanup job failed', [
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
        Log::error('Cleanup job failed permanently', [
            'error' => $exception->getMessage(),
        ]);
    }
}
