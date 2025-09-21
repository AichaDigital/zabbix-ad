<?php

namespace App\Services\Zabbix;

use App\Models\AuditLog;
use App\Models\ZabbixConnection;
use App\Models\ZabbixTemplate;
use Exception;
use Illuminate\Support\Facades\Log;

class TemplateManagementService
{
    private McpZabbixClient $client;

    private ZabbixConnection $connection;

    public function __construct(ZabbixConnection $connection)
    {
        $this->connection = $connection;
        $this->client = new McpZabbixClient($connection);
    }

    /**
     * Create a new template in Zabbix
     */
    public function createTemplate(array $templateData): array
    {
        $startTime = microtime(true);

        try {
            // Validate required fields
            $this->validateTemplateData($templateData);

            // Create template in Zabbix
            $result = $this->client->createTemplate($templateData);

            // Create local record
            $template = ZabbixTemplate::create([
                'zabbix_connection_id' => $this->connection->id,
                'template_id' => $result['templateid'] ?? null,
                'name' => $templateData['name'],
                'description' => $templateData['description'] ?? '',
                'template_type' => 'custom',
                'items_count' => 0,
                'triggers_count' => 0,
                'history_retention' => $templateData['history_retention'] ?? '7d',
                'trends_retention' => $templateData['trends_retention'] ?? '30d',
                'is_optimized' => false,
                'last_sync' => now(),
            ]);

            $executionTime = (int) round((microtime(true) - $startTime) * 1000);

            // Log successful creation
            AuditLog::logSuccess(
                $this->getCurrentUserId(),
                $this->connection->id,
                'create_template',
                'zabbix_template',
                $template->template_id,
                null,
                $templateData,
                $executionTime
            );

            Log::info('Template created successfully', [
                'connection_id' => $this->connection->id,
                'template_id' => $template->template_id,
                'template_name' => $template->name,
                'execution_time_ms' => $executionTime,
            ]);

            return [
                'success' => true,
                'template' => $template,
                'zabbix_result' => $result,
                'execution_time_ms' => $executionTime,
            ];

        } catch (Exception $e) {
            $executionTime = (int) round((microtime(true) - $startTime) * 1000);

            // Log failed creation
            AuditLog::logFailure(
                $this->getCurrentUserId(),
                $this->connection->id,
                'create_template',
                'zabbix_template',
                null,
                $e->getMessage(),
                $executionTime
            );

            Log::error('Template creation failed', [
                'connection_id' => $this->connection->id,
                'template_data' => $templateData,
                'error' => $e->getMessage(),
                'execution_time_ms' => $executionTime,
            ]);

            throw $e;
        }
    }

    /**
     * Create template from existing template
     */
    public function createTemplateFromExisting(ZabbixTemplate $sourceTemplate, array $newTemplateData): array
    {
        $startTime = microtime(true);

        try {
            // Get source template data
            $sourceData = $this->client->getTemplates();
            $sourceTemplateData = collect($sourceData)->firstWhere('templateid', $sourceTemplate->template_id);

            if (! $sourceTemplateData) {
                throw new Exception('Source template not found in Zabbix');
            }

            // Merge with new template data
            $templateData = array_merge($sourceTemplateData, $newTemplateData);

            // Create new template
            $result = $this->createTemplate($templateData);

            $executionTime = (int) round((microtime(true) - $startTime) * 1000);

            // Log template creation from existing
            AuditLog::logSuccess(
                $this->getCurrentUserId(),
                $this->connection->id,
                'create_template_from_existing',
                'zabbix_template',
                $result['template']->template_id,
                ['source_template_id' => $sourceTemplate->template_id],
                $newTemplateData,
                $executionTime
            );

            Log::info('Template created from existing template', [
                'connection_id' => $this->connection->id,
                'source_template_id' => $sourceTemplate->template_id,
                'new_template_id' => $result['template']->template_id,
                'execution_time_ms' => $executionTime,
            ]);

            return [
                'success' => true,
                'template' => $result['template'],
                'source_template' => $sourceTemplate,
                'execution_time_ms' => $executionTime,
            ];

        } catch (Exception $e) {
            $executionTime = (int) round((microtime(true) - $startTime) * 1000);

            Log::error('Template creation from existing failed', [
                'connection_id' => $this->connection->id,
                'source_template_id' => $sourceTemplate->template_id,
                'new_template_data' => $newTemplateData,
                'error' => $e->getMessage(),
                'execution_time_ms' => $executionTime,
            ]);

            throw $e;
        }
    }

    /**
     * Get template statistics
     */
    public function getTemplateStats(): array
    {
        try {
            $templates = ZabbixTemplate::where('zabbix_connection_id', $this->connection->id);
            $totalTemplates = $templates->count();

            return [
                'total_templates' => $totalTemplates,
                'system_templates' => $templates->clone()->byType('system')->count(),
                'custom_templates' => $templates->clone()->byType('custom')->count(),
                'imported_templates' => $templates->clone()->byType('imported')->count(),
                'optimized_templates' => $templates->clone()->optimized()->count(),
                'needs_optimization' => $templates->clone()->needsOptimization()->count(),
                'total_items' => $templates->clone()->sum('items_count'),
                'total_triggers' => $templates->sum('triggers_count'),
                'last_sync' => $templates->max('last_sync'),
            ];

        } catch (Exception $e) {
            Log::error('Failed to get template stats', [
                'connection_id' => $this->connection->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get templates by type
     */
    public function getTemplatesByType(string $type): array
    {
        try {
            $templates = ZabbixTemplate::where('zabbix_connection_id', $this->connection->id)
                ->byType($type)
                ->get();

            return [
                'success' => true,
                'type' => $type,
                'templates_count' => $templates->count(),
                'templates' => $templates,
            ];

        } catch (Exception $e) {
            Log::error('Failed to get templates by type', [
                'connection_id' => $this->connection->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get templates that need optimization
     */
    public function getTemplatesNeedingOptimization(): array
    {
        try {
            $templates = ZabbixTemplate::where('zabbix_connection_id', $this->connection->id)
                ->needsOptimization()
                ->get();

            return [
                'success' => true,
                'templates_count' => $templates->count(),
                'templates' => $templates,
            ];

        } catch (Exception $e) {
            Log::error('Failed to get templates needing optimization', [
                'connection_id' => $this->connection->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Analyze template optimization potential
     */
    public function analyzeTemplateOptimization(ZabbixTemplate $template): array
    {
        try {
            $analysis = $this->client->analyzeTemplateHistoryTrends($template->template_id);
            $potential = $template->getOptimizationPotential();

            return [
                'success' => true,
                'template_id' => $template->template_id,
                'template_name' => $template->name,
                'current_settings' => [
                    'history_retention' => $template->history_retention,
                    'trends_retention' => $template->trends_retention,
                ],
                'potential_savings' => $potential,
                'analysis' => $analysis,
            ];

        } catch (Exception $e) {
            Log::error('Failed to analyze template optimization', [
                'connection_id' => $this->connection->id,
                'template_id' => $template->template_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get template recommendations
     */
    public function getTemplateRecommendations(): array
    {
        try {
            $templates = ZabbixTemplate::where('zabbix_connection_id', $this->connection->id)
                ->needsOptimization()
                ->get();

            $recommendations = [];

            foreach ($templates as $template) {
                $potential = $template->getOptimizationPotential();

                if ($potential['history_reduction_percentage'] > 20 || $potential['trends_reduction_percentage'] > 20) {
                    $recommendations[] = [
                        'template_id' => $template->template_id,
                        'template_name' => $template->name,
                        'template_type' => $template->template_type,
                        'potential_savings' => $potential,
                        'priority' => $this->calculateOptimizationPriority($potential),
                    ];
                }
            }

            // Sort by priority
            usort($recommendations, fn ($a, $b) => $b['priority'] <=> $a['priority']);

            return [
                'success' => true,
                'recommendations_count' => count($recommendations),
                'recommendations' => $recommendations,
            ];

        } catch (Exception $e) {
            Log::error('Failed to get template recommendations', [
                'connection_id' => $this->connection->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Validate template data
     */
    private function validateTemplateData(array $templateData): void
    {
        $required = ['name'];
        $missing = [];

        foreach ($required as $field) {
            if (! isset($templateData[$field]) || empty($templateData[$field])) {
                $missing[] = $field;
            }
        }

        if (! empty($missing)) {
            throw new Exception('Missing required fields: '.implode(', ', $missing));
        }

        // Validate template name format
        if (! preg_match('/^[a-zA-Z0-9._-]+$/', $templateData['name'])) {
            throw new Exception('Invalid template name format. Only alphanumeric characters, dots, underscores, and hyphens are allowed.');
        }

        // Validate retention formats if provided
        if (isset($templateData['history_retention']) && ! $this->isValidRetentionFormat($templateData['history_retention'])) {
            throw new Exception('Invalid history retention format. Use format like "7d", "2w", "1M", "1y".');
        }

        if (isset($templateData['trends_retention']) && ! $this->isValidRetentionFormat($templateData['trends_retention'])) {
            throw new Exception('Invalid trends retention format. Use format like "7d", "2w", "1M", "1y".');
        }
    }

    /**
     * Validate retention format
     */
    private function isValidRetentionFormat(string $retention): bool
    {
        return (bool) preg_match('/^\d+[dwMy]$/', $retention);
    }

    /**
     * Calculate optimization priority
     */
    private function calculateOptimizationPriority(array $potential): int
    {
        $score = 0;

        // History reduction score
        $score += $potential['history_reduction_percentage'] * 2;

        // Trends reduction score
        $score += $potential['trends_reduction_percentage'] * 1.5;

        // Days reduction bonus
        $score += min($potential['history_reduction_days'] + $potential['trends_reduction_days'], 100);

        return (int) round($score);
    }

    /**
     * Get current user ID for audit logging
     */
    private function getCurrentUserId(): int
    {
        return 1; // Default user ID for system operations
    }
}
