<?php

namespace App\Services\Zabbix;

use App\Models\AuditLog;
use App\Models\ZabbixConnection;
use App\Models\ZabbixHost;
use App\Models\ZabbixTemplate;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ZabbixSyncService
{
    private McpZabbixClient $client;

    private ZabbixConnection $connection;

    public function __construct(ZabbixConnection $connection)
    {
        $this->connection = $connection;
        $this->client = new McpZabbixClient($connection);
    }

    /**
     * Allow injecting a custom MCP client (used by tests)
     */
    public function setClient(McpZabbixClient $client): void
    {
        $this->client = $client;
    }

    /**
     * Sync all data from Zabbix
     *
     * @return array{success: bool, results: array{templates: array{synced: int, errors: int}, hosts: array{synced: int, errors: int}}, execution_time_ms: int}
     */
    public function syncAll(): array
    {
        $startTime = microtime(true);
        $results = [
            'templates' => ['synced' => 0, 'errors' => 0],
            'hosts' => ['synced' => 0, 'errors' => 0],
        ];

        try {
            DB::beginTransaction();

            // Sync templates
            $templateResult = $this->syncTemplates();
            $results['templates'] = $templateResult;

            // Sync hosts
            $hostResult = $this->syncHosts();
            $results['hosts'] = $hostResult;

            // Update connection stats
            $this->updateConnectionStats();

            DB::commit();

            $executionTime = (int) round((microtime(true) - $startTime) * 1000);

            // Log successful sync
            AuditLog::logSuccess(
                $this->getCurrentUserId(),
                $this->connection->id,
                'sync_all',
                'zabbix_connection',
                (string) $this->connection->id,
                null,
                $results,
                $executionTime
            );

            Log::info('Zabbix sync completed successfully', [
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
            DB::rollBack();

            $executionTime = (int) round((microtime(true) - $startTime) * 1000);

            // Log failed sync
            AuditLog::logFailure(
                $this->getCurrentUserId(),
                $this->connection->id,
                'sync_all',
                'zabbix_connection',
                (string) $this->connection->id,
                $e->getMessage(),
                $executionTime
            );

            Log::error('Zabbix sync failed', [
                'connection_id' => $this->connection->id,
                'error' => $e->getMessage(),
                'execution_time_ms' => $executionTime,
            ]);

            throw $e;
        }
    }

    /**
     * Sync templates from Zabbix
     *
     * @return array{synced: int, errors: int}
     */
    public function syncTemplates(): array
    {
        $synced = 0;
        $errors = 0;

        try {
            $zabbixTemplates = $this->client->getTemplates();

            foreach ($zabbixTemplates as $templateData) {
                try {
                    $this->syncTemplate($templateData);
                    $synced++;
                } catch (Exception $e) {
                    $errors++;
                    Log::warning('Failed to sync template', [
                        'connection_id' => $this->connection->id,
                        'template_id' => $templateData['templateid'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return ['synced' => $synced, 'errors' => $errors];

        } catch (Exception $e) {
            Log::error('Failed to sync templates', [
                'connection_id' => $this->connection->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync a single template
     */
    /**
     * @param  array<string, mixed>  $templateData
     */
    private function syncTemplate(array $templateData): void
    {
        ZabbixTemplate::updateOrCreate(
            [
                'zabbix_connection_id' => $this->connection->id,
                'template_id' => $templateData['templateid'],
            ],
            [
                'name' => $templateData['name'] ?? '',
                'description' => $templateData['description'] ?? '',
                'template_type' => $this->determineTemplateType($templateData),
                'items_count' => $templateData['items_count'] ?? 0,
                'triggers_count' => $templateData['triggers_count'] ?? 0,
                'history_retention' => $templateData['history_retention'] ?? '7d',
                'trends_retention' => $templateData['trends_retention'] ?? '30d',
                'is_optimized' => $templateData['is_optimized'] ?? false,
                'last_sync' => now(),
            ]
        );
    }

    /**
     * Sync hosts from Zabbix
     *
     * @return array{synced: int, errors: int}
     */
    public function syncHosts(): array
    {
        $synced = 0;
        $errors = 0;

        try {
            $zabbixHosts = $this->client->getHosts();

            foreach ($zabbixHosts as $hostData) {
                try {
                    $this->syncHost($hostData);
                    $synced++;
                } catch (Exception $e) {
                    $errors++;
                    Log::warning('Failed to sync host', [
                        'connection_id' => $this->connection->id,
                        'host_id' => $hostData['hostid'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return ['synced' => $synced, 'errors' => $errors];

        } catch (Exception $e) {
            Log::error('Failed to sync hosts', [
                'connection_id' => $this->connection->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync a single host
     */
    /**
     * @param  array<string, mixed>  $hostData
     */
    private function syncHost(array $hostData): void
    {
        ZabbixHost::updateOrCreate(
            [
                'zabbix_connection_id' => $this->connection->id,
                'host_id' => $hostData['hostid'],
            ],
            [
                'host_name' => $hostData['host'] ?? '',
                'visible_name' => $hostData['name'] ?? '',
                'ip_address' => $this->extractIpAddress($hostData),
                'status' => $this->mapHostStatus((int) ($hostData['status'] ?? 0)),
                'available' => $this->mapHostAvailability((int) ($hostData['available'] ?? 0)),
                'templates_count' => is_array($hostData['parentTemplates'] ?? null) ? count($hostData['parentTemplates']) : 0,
                'items_count' => (int) ($hostData['items_count'] ?? 0),
                'last_check' => $this->parseZabbixTimestamp((int) ($hostData['lastcheck'] ?? 0)),
                'last_sync' => now(),
            ]
        );
    }

    /**
     * Update connection statistics
     */
    private function updateConnectionStats(): void
    {
        $stats = $this->client->getConnectionStats();

        $this->connection->update([
            'last_connection_test' => $stats['last_check'],
            'connection_status' => $stats['connection_status'],
        ]);
    }

    /**
     * Determine template type based on data
     */
    /**
     * @param  array<string, mixed>  $templateData
     */
    private function determineTemplateType(array $templateData): string
    {
        $name = strtolower((string) ($templateData['name'] ?? ''));

        if (str_contains($name, 'system') || str_contains($name, 'zabbix')) {
            return 'system';
        }

        if (str_contains($name, 'imported') || str_contains($name, 'import')) {
            return 'imported';
        }

        return 'custom';
    }

    /**
     * Extract IP address from host data
     */
    /**
     * @param  array<string, mixed>  $hostData
     */
    private function extractIpAddress(array $hostData): ?string
    {
        if (isset($hostData['interfaces']) && is_array($hostData['interfaces'])) {
            foreach ($hostData['interfaces'] as $interface) {
                if (is_array($interface) && isset($interface['ip']) && is_string($interface['ip']) && $interface['ip'] !== '') {
                    return $interface['ip'];
                }
            }
        }

        return null;
    }

    /**
     * Map Zabbix host status to our enum
     */
    private function mapHostStatus(int $status): string
    {
        return match ($status) {
            0 => 'enabled',
            1 => 'disabled',
            default => 'enabled',
        };
    }

    /**
     * Map Zabbix host availability to our enum
     */
    private function mapHostAvailability(int $available): string
    {
        return match ($available) {
            0 => 'unknown',
            1 => 'available',
            2 => 'unavailable',
            default => 'unknown',
        };
    }

    /**
     * Parse Zabbix timestamp to Carbon instance
     */
    private function parseZabbixTimestamp(int $timestamp): ?\Carbon\Carbon
    {
        if ($timestamp === 0) {
            return null;
        }

        return \Carbon\Carbon::createFromTimestamp($timestamp);
    }

    /**
     * Get sync statistics
     */
    /**
     * @return array<string, mixed>
     */
    public function getSyncStats(): array
    {
        return [
            'templates_count' => ZabbixTemplate::where('zabbix_connection_id', $this->connection->id)->count(),
            'hosts_count' => ZabbixHost::where('zabbix_connection_id', $this->connection->id)->count(),
            'last_sync' => ZabbixTemplate::where('zabbix_connection_id', $this->connection->id)->max('last_sync') ??
                          ZabbixHost::where('zabbix_connection_id', $this->connection->id)->max('last_sync'),
            'connection_status' => $this->connection->connection_status,
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
