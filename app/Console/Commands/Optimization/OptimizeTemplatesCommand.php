<?php

namespace App\Console\Commands\Optimization;

use App\Jobs\Zabbix\OptimizeTemplatesJob;
use App\Models\ZabbixConnection;
use App\Models\ZabbixTemplate;
use App\Services\Zabbix\TemplateOptimizationService;
use Illuminate\Console\Command;

class OptimizeTemplatesCommand extends Command
{
    protected $signature = 'zabbix:optimize:templates
                            {connection : ID or name of the connection}
                            {template? : ID or name of specific template to optimize}
                            {--all : Optimize all templates that need optimization}
                            {--auto : Use auto-optimization via MCP server}
                            {--queue : Run optimization in background queue}
                            {--force : Force optimization even if already optimized}';

    protected $description = 'Optimize Zabbix templates';

    public function handle(): int
    {
        $connection = $this->getConnection();
        if (! $connection) {
            return self::FAILURE;
        }

        if ($this->option('all')) {
            return $this->optimizeAllTemplates($connection);
        }

        $template = $this->getTemplate($connection);
        if (! $template) {
            return self::FAILURE;
        }

        if ($this->option('queue')) {
            return $this->queueOptimization($connection, $template);
        }

        return $this->optimizeTemplate($connection, $template);
    }

    private function optimizeAllTemplates(ZabbixConnection $connection): int
    {
        $this->info("Optimizing all templates for connection: {$connection->name}");

        if ($this->option('auto')) {
            return $this->autoOptimizeAll($connection);
        }

        $templates = ZabbixTemplate::where('zabbix_connection_id', $connection->id)->needsOptimization()->get();

        if ($templates->isEmpty()) {
            $this->warn('No templates need optimization.');

            return self::SUCCESS;
        }

        $this->line("Found {$templates->count()} templates needing optimization");
        $this->newLine();

        if ($this->option('queue')) {
            OptimizeTemplatesJob::dispatch($connection, null, false);
            $this->info('✓ Optimization job queued successfully!');

            return self::SUCCESS;
        }

        return $this->optimizeTemplatesBatch($connection, $templates);
    }

    private function autoOptimizeAll(ZabbixConnection $connection): int
    {
        $this->info('Running auto-optimization via MCP server...');

        if ($this->option('queue')) {
            OptimizeTemplatesJob::dispatch($connection, null, true);
            $this->info('✓ Auto-optimization job queued successfully!');

            return self::SUCCESS;
        }

        try {
            $optimizationService = new TemplateOptimizationService($connection);

            $bar = $this->output->createProgressBar(1);
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
            $bar->setMessage('Running auto-optimization...');
            $bar->start();

            $result = $optimizationService->autoOptimizeAllTemplates();

            $bar->finish();
            $this->newLine(2);

            $this->info('✓ Auto-optimization completed successfully!');
            $this->line('Execution time: '.(is_scalar($result['execution_time_ms']) ? (string) $result['execution_time_ms'] : '0').'ms');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Auto-optimization failed!');
            $this->line("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function optimizeTemplate(ZabbixConnection $connection, ZabbixTemplate $template): int
    {
        $this->info("Optimizing template: {$template->name}");
        $this->line("Connection: {$connection->name}");
        $this->newLine();

        if ($template->is_optimized && ! $this->option('force')) {
            $this->warn('Template is already optimized. Use --force to optimize anyway.');

            return self::SUCCESS;
        }

        try {
            $optimizationService = new TemplateOptimizationService($connection);

            $bar = $this->output->createProgressBar(3);
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
            $bar->setMessage('Analyzing template...');
            $bar->start();

            $analysis = $optimizationService->analyzeTemplate($template);
            $bar->advance();
            $bar->setMessage('Applying optimization...');

            $result = $optimizationService->optimizeTemplate($template);
            $bar->advance();
            $bar->setMessage('Finalizing...');

            $bar->finish();
            $this->newLine(2);

            $this->info('✓ Template optimization completed successfully!');
            $this->line('Execution time: '.(is_scalar($result['execution_time_ms']) ? (string) $result['execution_time_ms'] : '0').'ms');
            $this->newLine();

            $this->displayOptimizationResults($analysis, $result);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Template optimization failed!');
            $this->line("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Models\ZabbixTemplate>  $templates
     */
    private function optimizeTemplatesBatch(ZabbixConnection $connection, \Illuminate\Database\Eloquent\Collection $templates): int
    {
        $successCount = 0;
        $errorCount = 0;
        $totalCount = $templates->count();

        $bar = $this->output->createProgressBar($totalCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

        foreach ($templates as $template) {
            $bar->setMessage('Optimizing: '.($template->name ?? 'Unknown'));

            try {
                $optimizationService = new TemplateOptimizationService($connection);
                $optimizationService->optimizeTemplate($template);
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $this->newLine();
                $this->warn('Failed to optimize '.($template->name ?? 'Unknown').": {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Batch optimization completed!');
        $this->line("Successful: {$successCount}");
        $this->line("Failed: {$errorCount}");
        $this->line("Total: {$totalCount}");

        return $errorCount === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function queueOptimization(ZabbixConnection $connection, ?ZabbixTemplate $template = null): int
    {
        $this->info("Queuing optimization for connection: {$connection->name}");

        OptimizeTemplatesJob::dispatch($connection, $template, false);

        $this->info('✓ Optimization job queued successfully!');
        $this->line('Use "php artisan queue:work" to process the job.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @param  array<string, mixed>  $result
     */
    private function displayOptimizationResults(array $analysis, array $result): void
    {
        $this->line('<fg=cyan>Optimization Results:</>');
        $this->line('  Template: '.(is_scalar($result['template_name'] ?? null) ? (string) $result['template_name'] : 'Unknown'));

        $currentSettings = $analysis['current_settings'] ?? [];
        $optimizationSettings = $result['optimization_settings'] ?? [];

        $currentHistory = is_array($currentSettings) && is_scalar($currentSettings['history_retention'] ?? null) ? (string) $currentSettings['history_retention'] : 'N/A';
        $newHistory = is_array($optimizationSettings) && is_scalar($optimizationSettings['history_to'] ?? null) ? (string) $optimizationSettings['history_to'] : 'N/A';
        $this->line("  History: {$currentHistory} → {$newHistory}");

        $currentTrends = is_array($currentSettings) && is_scalar($currentSettings['trends_retention'] ?? null) ? (string) $currentSettings['trends_retention'] : 'N/A';
        $newTrends = is_array($optimizationSettings) && is_scalar($optimizationSettings['trends_to'] ?? null) ? (string) $optimizationSettings['trends_to'] : 'N/A';
        $this->line("  Trends: {$currentTrends} → {$newTrends}");

        $potential = $analysis['potential_savings'] ?? [];
        $savings = is_array($potential) && is_scalar($potential['total_storage_savings'] ?? null) ? (string) $potential['total_storage_savings'] : '0';
        $this->line("  Storage Savings: {$savings}%");
    }

    private function getConnection(): ?ZabbixConnection
    {
        $identifier = $this->argument('connection');

        $connection = ZabbixConnection::find($identifier)
            ?? ZabbixConnection::where('name', $identifier)->first();

        if (! $connection) {
            $this->error("Connection not found: {$identifier}");

            return null;
        }

        return $connection;
    }

    private function getTemplate(ZabbixConnection $connection): ?ZabbixTemplate
    {
        $identifier = $this->argument('template');

        if (! $identifier) {
            $templates = ZabbixTemplate::where('zabbix_connection_id', $connection->id)->needsOptimization()->get();

            if ($templates->isEmpty()) {
                $this->warn('No templates need optimization.');

                return null;
            }

            $templateName = $this->choice(
                'Select a template to optimize:',
                $templates->pluck('name')->toArray()
            );

            return $templates->firstWhere('name', $templateName);
        }

        $template = ZabbixTemplate::where('zabbix_connection_id', $connection->id)
            ->where('template_id', $identifier)
            ->orWhere('name', $identifier)
            ->first();

        if (! $template) {
            $this->error("Template not found: {$identifier}");

            return null;
        }

        return $template;
    }
}
