<?php

namespace App\Console\Commands\Template;

use App\Models\ZabbixConnection;
use App\Models\ZabbixTemplate;
use App\Services\Zabbix\TemplateOptimizationService;
use Illuminate\Console\Command;

class AnalyzeTemplateCommand extends Command
{
    protected $signature = 'zabbix:template:analyze
                            {connection : ID or name of the connection}
                            {template : ID or name of the template to analyze}';

    protected $description = 'Analyze template optimization potential';

    public function handle(): int
    {
        $connection = $this->getConnection();
        if (! $connection) {
            return self::FAILURE;
        }

        $template = $this->getTemplate($connection);
        if (! $template) {
            return self::FAILURE;
        }

        $this->info("Analyzing template: {$template->name}");
        $this->line("Connection: {$connection->name}");
        $this->line("Template ID: {$template->template_id}");
        $this->newLine();

        try {
            $optimizationService = new TemplateOptimizationService($connection);

            $this->info('Analyzing template optimization potential...');
            $bar = $this->output->createProgressBar(1);
            $bar->start();

            $analysis = $optimizationService->analyzeTemplate($template);

            $bar->finish();
            $this->newLine(2);

            $this->displayAnalysis($analysis);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Analysis failed!');
            $this->line("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function displayAnalysis(array $analysis): void
    {
        $this->info('📊 Analysis Results:');
        $this->newLine();

        // Current settings
        $this->line('<fg=cyan>Current Settings:</>');
        $currentSettings = $analysis['current_settings'] ?? [];
        $historyRetention = is_array($currentSettings) && is_scalar($currentSettings['history_retention'] ?? null) ? (string) $currentSettings['history_retention'] : 'N/A';
        $trendsRetention = is_array($currentSettings) && is_scalar($currentSettings['trends_retention'] ?? null) ? (string) $currentSettings['trends_retention'] : 'N/A';
        $this->line("  History Retention: {$historyRetention}");
        $this->line("  Trends Retention: {$trendsRetention}");
        $this->newLine();

        // Optimization settings
        $this->line('<fg=cyan>Recommended Settings:</>');
        $optimizationSettings = $analysis['optimization_settings'] ?? [];
        $historyTo = is_array($optimizationSettings) && is_scalar($optimizationSettings['history_to'] ?? null) ? (string) $optimizationSettings['history_to'] : 'N/A';
        $trendsTo = is_array($optimizationSettings) && is_scalar($optimizationSettings['trends_to'] ?? null) ? (string) $optimizationSettings['trends_to'] : 'N/A';
        $this->line("  History Retention: {$historyTo}");
        $this->line("  Trends Retention: {$trendsTo}");
        $this->newLine();

        // Potential savings
        $potential = $analysis['potential_savings'] ?? [];
        $this->line('<fg=cyan>Potential Savings:</>');
        $historyReduction = is_array($potential) && is_scalar($potential['history_reduction_percentage'] ?? null) ? (string) $potential['history_reduction_percentage'] : '0';
        $historyDays = is_array($potential) && is_scalar($potential['history_reduction_days'] ?? null) ? (string) $potential['history_reduction_days'] : '0';
        $trendsReduction = is_array($potential) && is_scalar($potential['trends_reduction_percentage'] ?? null) ? (string) $potential['trends_reduction_percentage'] : '0';
        $trendsDays = is_array($potential) && is_scalar($potential['trends_reduction_days'] ?? null) ? (string) $potential['trends_reduction_days'] : '0';
        $totalSavings = is_array($potential) && is_scalar($potential['total_storage_savings'] ?? null) ? (string) $potential['total_storage_savings'] : '0';
        $this->line("  History Reduction: {$historyReduction}% ({$historyDays} days)");
        $this->line("  Trends Reduction: {$trendsReduction}% ({$trendsDays} days)");
        $this->line("  Total Storage Savings: {$totalSavings}%");
        $this->newLine();

        // Analysis details
        if (isset($analysis['analysis']) && is_array($analysis['analysis'])) {
            $this->line('<fg=cyan>Detailed Analysis:</>');
            foreach ($analysis['analysis'] as $key => $value) {
                if (is_array($value)) {
                    $this->line("  {$key}: ".json_encode($value, JSON_PRETTY_PRINT));
                } else {
                    $this->line("  {$key}: ".(is_scalar($value) ? (string) $value : 'N/A'));
                }
            }
        }

        // Recommendations
        $this->newLine();
        $this->showRecommendations(is_array($potential) ? $potential : []);
    }

    /**
     * @param  array<string, mixed>  $potential
     */
    private function showRecommendations(array $potential): void
    {
        $this->line('<fg=cyan>Recommendations:</>');

        if ($potential['history_reduction_percentage'] > 20) {
            $this->line('  <fg=green>✓</> High history retention reduction potential');
        } elseif ($potential['history_reduction_percentage'] > 10) {
            $this->line('  <fg=yellow>⚠</> Moderate history retention reduction potential');
        } else {
            $this->line('  <fg=red>✗</> Low history retention reduction potential');
        }

        if ($potential['trends_reduction_percentage'] > 20) {
            $this->line('  <fg=green>✓</> High trends retention reduction potential');
        } elseif ($potential['trends_reduction_percentage'] > 10) {
            $this->line('  <fg=yellow>⚠</> Moderate trends retention reduction potential');
        } else {
            $this->line('  <fg=red>✗</> Low trends retention reduction potential');
        }

        if ($potential['total_storage_savings'] > 30) {
            $this->line('  <fg=green>✓</> High overall optimization potential');
        } elseif ($potential['total_storage_savings'] > 15) {
            $this->line('  <fg=yellow>⚠</> Moderate optimization potential');
        } else {
            $this->line('  <fg=red>✗</> Low optimization potential');
        }
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
