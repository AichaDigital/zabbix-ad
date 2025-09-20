<?php

namespace App\Console\Commands\Maintenance;

use App\Models\AuditLog;
use App\Models\BackgroundJob;
use App\Models\ZabbixConnection;
use App\Models\ZabbixHost;
use App\Models\ZabbixTemplate;
use Illuminate\Console\Command;

class StatusCommand extends Command
{
    protected $signature = 'zabbix:maintenance:status
                            {--detailed : Show detailed status information}';

    protected $description = 'Show system status and statistics';

    public function handle(): int
    {
        $this->info('🔍 Zabbix Management Platform Status');
        $this->newLine();

        $this->showConnectionsStatus();
        $this->showTemplatesStatus();
        $this->showHostsStatus();
        $this->showJobsStatus();
        $this->showAuditLogsStatus();

        if ($this->option('detailed')) {
            $this->newLine();
            $this->showDetailedStatus();
        }

        return self::SUCCESS;
    }

    private function showConnectionsStatus(): void
    {
        $this->line('<fg=cyan>📡 Connections:</>');

        $connections = ZabbixConnection::all();
        $activeConnections = $connections->where('is_active', true);
        $errorConnections = $connections->where('connection_status', 'error');

        $this->line("  Total: {$connections->count()}");
        $this->line("  Active: {$activeConnections->count()}");
        $this->line("  Errors: {$errorConnections->count()}");

        if ($errorConnections->count() > 0) {
            $this->line('  <fg=red>⚠ Connections with errors:</>');
            foreach ($errorConnections as $connection) {
                $this->line("    - {$connection->name} ({$connection->url})");
            }
        }

        $this->newLine();
    }

    private function showTemplatesStatus(): void
    {
        $this->line('<fg=cyan>📋 Templates:</>');

        $templates = ZabbixTemplate::all();
        $optimizedTemplates = $templates->where('is_optimized', true);
        $needsOptimization = $templates->where('is_optimized', false);

        $this->line("  Total: {$templates->count()}");
        $this->line("  Optimized: {$optimizedTemplates->count()}");
        $this->line("  Needs Optimization: {$needsOptimization->count()}");
        $this->line('  Total Items: '.(is_numeric($templates->sum('items_count')) ? (string) $templates->sum('items_count') : '0'));
        $this->line('  Total Triggers: '.(is_numeric($templates->sum('triggers_count')) ? (string) $templates->sum('triggers_count') : '0'));

        $this->newLine();
    }

    private function showHostsStatus(): void
    {
        $this->line('<fg=cyan>🖥️ Hosts:</>');

        $hosts = ZabbixHost::all();
        $enabledHosts = $hosts->where('status', 'enabled');
        $availableHosts = $hosts->where('available', 'available');
        $healthyHosts = $hosts->filter(fn ($host) => $host->isHealthy());

        $this->line("  Total: {$hosts->count()}");
        $this->line("  Enabled: {$enabledHosts->count()}");
        $this->line("  Available: {$availableHosts->count()}");
        $this->line("  Healthy: {$healthyHosts->count()}");
        $this->line('  Total Templates: '.(is_numeric($hosts->sum('templates_count')) ? (string) $hosts->sum('templates_count') : '0'));
        $this->line('  Total Items: '.(is_numeric($hosts->sum('items_count')) ? (string) $hosts->sum('items_count') : '0'));

        $this->newLine();
    }

    private function showJobsStatus(): void
    {
        $this->line('<fg=cyan>⚙️ Background Jobs:</>');

        $jobs = BackgroundJob::all();
        $runningJobs = $jobs->where('status', 'running');
        $pendingJobs = $jobs->where('status', 'pending');
        $failedJobs = $jobs->where('status', 'failed');

        $this->line("  Total: {$jobs->count()}");
        $this->line("  Running: {$runningJobs->count()}");
        $this->line("  Pending: {$pendingJobs->count()}");
        $this->line("  Failed: {$failedJobs->count()}");

        if ($failedJobs->count() > 0) {
            $this->line('  <fg=red>⚠ Failed jobs:</>');
            foreach ($failedJobs->take(5) as $job) {
                $this->line("    - {$job->job_type} (ID: {$job->id})");
            }
            if ($failedJobs->count() > 5) {
                $this->line('    ... and '.($failedJobs->count() - 5).' more');
            }
        }

        $this->newLine();
    }

    private function showAuditLogsStatus(): void
    {
        $this->line('<fg=cyan>📊 Audit Logs:</>');

        $auditLogs = AuditLog::all();
        $successfulLogs = $auditLogs->where('status', 'success');
        $failedLogs = $auditLogs->where('status', 'failed');

        $this->line("  Total: {$auditLogs->count()}");
        $this->line("  Successful: {$successfulLogs->count()}");
        $this->line("  Failed: {$failedLogs->count()}");

        $this->newLine();
    }

    private function showDetailedStatus(): void
    {
        $this->line('<fg=cyan>📈 Detailed Statistics:</>');

        // Connection statistics by environment
        $connectionsByEnv = ZabbixConnection::selectRaw('environment, COUNT(*) as count')
            ->groupBy('environment')
            ->get();

        $this->line('  Connections by Environment:');
        foreach ($connectionsByEnv as $env) {
            // @phpstan-ignore-next-line
            $this->line("    {$env->environment}: ".(is_numeric($env->count) ? (string) $env->count : '0'));
        }

        // Template statistics by type
        $templatesByType = ZabbixTemplate::selectRaw('template_type, COUNT(*) as count')
            ->groupBy('template_type')
            ->get();

        $this->line('  Templates by Type:');
        foreach ($templatesByType as $type) {
            // @phpstan-ignore-next-line
            $this->line("    {$type->template_type}: ".(is_numeric($type->count) ? (string) $type->count : '0'));
        }

        // Host statistics by status
        $hostsByStatus = ZabbixHost::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $this->line('  Hosts by Status:');
        foreach ($hostsByStatus as $status) {
            // @phpstan-ignore-next-line
            $this->line("    {$status->status}: ".(is_numeric($status->count) ? (string) $status->count : '0'));
        }

        // Recent activity
        $recentJobs = BackgroundJob::where('created_at', '>=', now()->subHours(24))
            ->count();

        $recentAuditLogs = AuditLog::where('created_at', '>=', now()->subHours(24))
            ->count();

        $this->line('  Recent Activity (24h):');
        $this->line("    Jobs: {$recentJobs}");
        $this->line("    Audit Logs: {$recentAuditLogs}");

        $this->newLine();
    }
}
