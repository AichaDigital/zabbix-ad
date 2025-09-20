<?php

namespace App\Services\Zabbix;

use App\Models\AuditLog;
use App\Models\TemplateOptimizationRule;
use App\Models\ZabbixConnection;
use App\Models\ZabbixTemplate;
use Exception;
use Illuminate\Support\Facades\Log;

class TemplateOptimizationService
{
    private McpZabbixClient $client;

    private ZabbixConnection $connection;

    public function __construct(ZabbixConnection $connection)
    {
        $this->connection = $connection;
        $this->client = new McpZabbixClient($connection);
    }

    /**
     * Optimize a specific template
     */
    /**
     * @param  array<string, mixed>|null  $optimizationSettings
     * @return array<string, mixed>
     */
    public function optimizeTemplate(ZabbixTemplate $template, ?array $optimizationSettings = null): array
    {
        $startTime = microtime(true);

        try {
            // Get optimization settings if not provided
            if (! $optimizationSettings) {
                $optimizationSettings = $this->getOptimizationSettings($template);
            }

            // Analyze current template
            $analysis = $this->client->analyzeTemplateHistoryTrends($template->template_id);

            // Apply optimization
            $result = $this->client->updateTemplateHistoryTrends(
                $template->template_id,
                $optimizationSettings
            );

            // Update template status
            $template->markAsOptimized();

            $executionTime = round((microtime(true) - $startTime) * 1000);

            // Log successful optimization
            AuditLog::logSuccess(
                $this->getCurrentUserId(),
                $this->connection->id,
                'optimize_template',
                'zabbix_template',
                $template->template_id,
                $analysis,
                $result,
                (int) $executionTime
            );

            Log::info('Template optimization completed', [
                'connection_id' => $this->connection->id,
                'template_id' => $template->template_id,
                'template_name' => $template->name,
                'optimization_settings' => $optimizationSettings,
                'execution_time_ms' => $executionTime,
            ]);

            return [
                'success' => true,
                'template_id' => $template->template_id,
                'template_name' => $template->name,
                'optimization_settings' => $optimizationSettings,
                'analysis' => $analysis,
                'result' => $result,
                'execution_time_ms' => $executionTime,
            ];

        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000);

            // Log failed optimization
            AuditLog::logFailure(
                $this->getCurrentUserId(),
                $this->connection->id,
                'optimize_template',
                'zabbix_template',
                $template->template_id,
                $e->getMessage(),
                (int) $executionTime
            );

            Log::error('Template optimization failed', [
                'connection_id' => $this->connection->id,
                'template_id' => $template->template_id,
                'template_name' => $template->name,
                'error' => $e->getMessage(),
                'execution_time_ms' => $executionTime,
            ]);

            throw $e;
        }
    }

    /**
     * Optimize all templates that need optimization
     */
    /**
     * @return array<string, mixed>
     */
    public function optimizeAllTemplates(): array
    {
        $startTime = microtime(true);
        $results = [
            'optimized' => 0,
            'skipped' => 0,
            'errors' => 0,
            'templates' => [],
        ];

        try {
            // Get templates that need optimization
            $templates = ZabbixTemplate::where('zabbix_connection_id', $this->connection->id)
                ->needsOptimization()
                ->get();

            foreach ($templates as $template) {
                try {
                    $result = $this->optimizeTemplate($template);
                    $results['optimized']++;
                    $results['templates'][] = [
                        'template_id' => $template->template_id,
                        'template_name' => $template->name,
                        'status' => 'optimized',
                        'result' => $result,
                    ];
                } catch (Exception $e) {
                    $results['errors']++;
                    $results['templates'][] = [
                        'template_id' => $template->template_id,
                        'template_name' => $template->name,
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $executionTime = round((microtime(true) - $startTime) * 1000);

            // Log batch optimization
            AuditLog::logSuccess(
                $this->getCurrentUserId(),
                $this->connection->id,
                'optimize_all_templates',
                'zabbix_connection',
                $this->connection->id,
                null,
                $results,
                (int) $executionTime
            );

            Log::info('Batch template optimization completed', [
                'connection_id' => $this->connection->id,
                'results' => $results,
                'execution_time_ms' => $executionTime,
            ]);

            return [
                'success' => true,
                'results' => $results,
                'execution_time_ms' => $executionTime,
            ];

        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000);

            Log::error('Batch template optimization failed', [
                'connection_id' => $this->connection->id,
                'error' => $e->getMessage(),
                'execution_time_ms' => $executionTime,
            ]);

            throw $e;
        }
    }

    /**
     * Auto-optimize all templates using MCP server
     */
    /**
     * @return array<string, mixed>
     */
    public function autoOptimizeAllTemplates(): array
    {
        $startTime = microtime(true);

        try {
            $result = $this->client->updateAllTemplateHistoryTrendsAuto();

            // Update all templates as optimized
            ZabbixTemplate::where('zabbix_connection_id', $this->connection->id)
                ->needsOptimization()
                ->update([
                    'is_optimized' => true,
                    'last_sync' => now(),
                ]);

            $executionTime = round((microtime(true) - $startTime) * 1000);

            // Log auto optimization
            AuditLog::logSuccess(
                $this->getCurrentUserId(),
                $this->connection->id,
                'auto_optimize_all_templates',
                'zabbix_connection',
                $this->connection->id,
                null,
                $result,
                (int) $executionTime
            );

            Log::info('Auto template optimization completed', [
                'connection_id' => $this->connection->id,
                'result' => $result,
                'execution_time_ms' => $executionTime,
            ]);

            return [
                'success' => true,
                'result' => $result,
                'execution_time_ms' => $executionTime,
            ];

        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000);

            // Log failed auto optimization
            AuditLog::logFailure(
                $this->getCurrentUserId(),
                $this->connection->id,
                'auto_optimize_all_templates',
                'zabbix_connection',
                $this->connection->id,
                $e->getMessage(),
                (int) $executionTime
            );

            Log::error('Auto template optimization failed', [
                'connection_id' => $this->connection->id,
                'error' => $e->getMessage(),
                'execution_time_ms' => $executionTime,
            ]);

            throw $e;
        }
    }

    /**
     * Get optimization settings for a template
     */
    private function getOptimizationSettings(ZabbixTemplate $template): array
    {
        // Find applicable optimization rules
        $rules = TemplateOptimizationRule::active()
            ->byEnvironment($this->connection->environment)
            ->matchingTemplate($template->name)
            ->get();

        if ($rules->isEmpty()) {
            // Use default settings
            return [
                'history_from' => $template->history_retention,
                'history_to' => '7d',
                'trends_from' => $template->trends_retention,
                'trends_to' => '30d',
            ];
        }

        // Use the first matching rule
        $rule = $rules->first();

        return $rule->getOptimizationSettings();
    }

    /**
     * Analyze template optimization potential
     */
    /**
     * @return array<string, mixed>
     */
    public function analyzeTemplate(ZabbixTemplate $template): array
    {
        try {
            $analysis = $this->client->analyzeTemplateHistoryTrends($template->template_id);
            $optimizationSettings = $this->getOptimizationSettings($template);
            $potential = $template->getOptimizationPotential();

            return [
                'template_id' => $template->template_id,
                'template_name' => $template->name,
                'current_settings' => [
                    'history_retention' => $template->history_retention,
                    'trends_retention' => $template->trends_retention,
                ],
                'optimization_settings' => $optimizationSettings,
                'potential_savings' => $potential,
                'analysis' => $analysis,
            ];

        } catch (Exception $e) {
            Log::error('Failed to analyze template', [
                'connection_id' => $this->connection->id,
                'template_id' => $template->template_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get optimization statistics
     */
    /**
     * @return array<string, mixed>
     */
    public function getOptimizationStats(): array
    {
        $templates = ZabbixTemplate::where('zabbix_connection_id', $this->connection->id);
        $totalTemplates = $templates->count();
        $optimizedTemplates = $templates->clone()->optimized()->count();
        $needsOptimization = $templates->clone()->needsOptimization()->count();

        return [
            'total_templates' => $totalTemplates,
            'optimized_templates' => $optimizedTemplates,
            'needs_optimization' => $needsOptimization,
            'optimization_percentage' => $totalTemplates > 0
                ? round(($optimizedTemplates / $totalTemplates) * 100, 2)
                : 0,
            'last_optimization' => $templates->clone()->optimized()->max('last_sync'),
        ];
    }

    /**
     * Get current user ID for audit logging
     */
    private function getCurrentUserId(): int
    {
        return 1; // Default user ID for system operations
    }
}
