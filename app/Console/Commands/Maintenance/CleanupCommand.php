<?php

namespace App\Console\Commands\Maintenance;

use App\Jobs\Zabbix\CleanupOldJobsJob;
use App\Models\AuditLog;
use App\Models\BackgroundJob;
use Illuminate\Console\Command;

class CleanupCommand extends Command
{
    protected $signature = 'zabbix:maintenance:cleanup
                            {--days=30 : Number of days to keep data}
                            {--jobs : Clean up old background jobs}
                            {--audit : Clean up old audit logs}
                            {--all : Clean up all data types}
                            {--queue : Run cleanup in background queue}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Clean up old Zabbix management data';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up data older than {$days} days (before {$cutoffDate->format('Y-m-d H:i:s')})");
        $this->newLine();

        if ($this->option('queue')) {
            return $this->queueCleanup($days);
        }

        $totalDeleted = 0;

        if ($this->option('all') || $this->option('jobs')) {
            $deletedJobs = $this->cleanupJobs($cutoffDate);
            $totalDeleted += $deletedJobs;
        }

        if ($this->option('all') || $this->option('audit')) {
            $deletedAuditLogs = $this->cleanupAuditLogs($cutoffDate);
            $totalDeleted += $deletedAuditLogs;
        }

        if (! $this->option('all') && ! $this->option('jobs') && ! $this->option('audit')) {
            $this->error('Please specify --jobs, --audit, or --all option.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Cleanup completed. Total records deleted: {$totalDeleted}");

        return self::SUCCESS;
    }

    private function cleanupJobs(\Carbon\Carbon $cutoffDate): int
    {
        $this->line('<fg=cyan>Cleaning up old background jobs...</>');

        $query = BackgroundJob::where('created_at', '<', $cutoffDate)
            ->whereIn('status', ['completed', 'failed', 'cancelled']);

        $count = $query->count();

        if ($count === 0) {
            $this->line('  No old jobs to clean up.');

            return 0;
        }

        if ($this->option('dry-run')) {
            $this->line("  Would delete {$count} old jobs.");

            return 0;
        }

        $deleted = $query->delete();
        $this->line('  Deleted '.(is_numeric($deleted) ? (string) $deleted : '0').' old jobs.');

        return is_numeric($deleted) ? (int) $deleted : 0;
    }

    private function cleanupAuditLogs(\Carbon\Carbon $cutoffDate): int
    {
        $this->line('<fg=cyan>Cleaning up old audit logs...</>');

        $query = AuditLog::where('created_at', '<', $cutoffDate);
        $count = $query->count();

        if ($count === 0) {
            $this->line('  No old audit logs to clean up.');

            return 0;
        }

        if ($this->option('dry-run')) {
            $this->line("  Would delete {$count} old audit logs.");

            return 0;
        }

        $deleted = $query->delete();
        $this->line('  Deleted '.(is_numeric($deleted) ? (string) $deleted : '0').' old audit logs.');

        return is_numeric($deleted) ? (int) $deleted : 0;
    }

    private function queueCleanup(int $days): int
    {
        $this->info('Queuing cleanup job...');

        CleanupOldJobsJob::dispatch($days);

        $this->info('✓ Cleanup job queued successfully!');
        $this->line('Use "php artisan queue:work" to process the job.');

        return self::SUCCESS;
    }
}
