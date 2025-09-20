<?php

namespace App\Services\Zabbix;

use App\Models\AuditLog;
use App\Models\ZabbixConnection;
use App\Models\ZabbixHost;
use Exception;
use Illuminate\Support\Facades\Log;

class HostManagementService
{
    private McpZabbixClient $client;

    private ZabbixConnection $connection;

    public function __construct(ZabbixConnection $connection)
    {
        $this->connection = $connection;
        $this->client = new McpZabbixClient($connection);
    }

    /**
     * Create a new host in Zabbix
     */
    public function createHost(array $hostData): array
    {
        $startTime = microtime(true);

        try {
            // Validate required fields
            $this->validateHostData($hostData);

            // Create host in Zabbix
            $result = $this->client->createHost($hostData);

            // Create local record
            $host = ZabbixHost::create([
                'zabbix_connection_id' => $this->connection->id,
                'host_id' => $result['hostid'] ?? null,
                'host_name' => $hostData['host'],
                'visible_name' => $hostData['name'] ?? $hostData['host'],
                'ip_address' => $hostData['ip'] ?? null,
                'status' => 'enabled',
                'available' => 'unknown',
                'templates_count' => count($hostData['templates'] ?? []),
                'items_count' => 0,
                'last_sync' => now(),
            ]);

            $executionTime = round((microtime(true) - $startTime) * 1000);

            // Log successful creation
            AuditLog::logSuccess(
                $this->getCurrentUserId(),
                $this->connection->id,
                'create_host',
                'zabbix_host',
                $host->host_id,
                null,
                $hostData,
                $executionTime
            );

            Log::info('Host created successfully', [
                'connection_id' => $this->connection->id,
                'host_id' => $host->host_id,
                'host_name' => $host->host_name,
                'execution_time_ms' => $executionTime,
            ]);

            return [
                'success' => true,
                'host' => $host,
                'zabbix_result' => $result,
                'execution_time_ms' => $executionTime,
            ];

        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000);

            // Log failed creation
            AuditLog::logFailure(
                $this->getCurrentUserId(),
                $this->connection->id,
                'create_host',
                'zabbix_host',
                null,
                $e->getMessage(),
                $executionTime
            );

            Log::error('Host creation failed', [
                'connection_id' => $this->connection->id,
                'host_data' => $hostData,
                'error' => $e->getMessage(),
                'execution_time_ms' => $executionTime,
            ]);

            throw $e;
        }
    }

    /**
     * Create multiple hosts in batch
     */
    public function createHostsBatch(array $hostsData): array
    {
        $startTime = microtime(true);
        $results = [
            'created' => 0,
            'errors' => 0,
            'hosts' => [],
        ];

        foreach ($hostsData as $index => $hostData) {
            try {
                $result = $this->createHost($hostData);
                $results['created']++;
                $results['hosts'][] = [
                    'index' => $index,
                    'host_name' => $hostData['host'],
                    'status' => 'created',
                    'host_id' => $result['host']->host_id,
                ];
            } catch (Exception $e) {
                $results['errors']++;
                $results['hosts'][] = [
                    'index' => $index,
                    'host_name' => $hostData['host'] ?? 'unknown',
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $executionTime = round((microtime(true) - $startTime) * 1000);

        // Log batch creation
        AuditLog::logSuccess(
            $this->getCurrentUserId(),
            $this->connection->id,
            'create_hosts_batch',
            'zabbix_connection',
            $this->connection->id,
            null,
            $results,
            $executionTime
        );

        Log::info('Batch host creation completed', [
            'connection_id' => $this->connection->id,
            'results' => $results,
            'execution_time_ms' => $executionTime,
        ]);

        return [
            'success' => true,
            'results' => $results,
            'execution_time_ms' => $executionTime,
        ];
    }

    /**
     * Update host statistics
     */
    public function updateHostStats(ZabbixHost $host): array
    {
        try {
            $stats = $this->client->getConnectionStats();

            $host->updateStatistics([
                'templates_count' => $stats['templates_count'] ?? $host->templates_count,
                'items_count' => $stats['items_count'] ?? $host->items_count,
            ]);

            return [
                'success' => true,
                'host_id' => $host->host_id,
                'updated_stats' => $stats,
            ];

        } catch (Exception $e) {
            Log::error('Failed to update host stats', [
                'connection_id' => $this->connection->id,
                'host_id' => $host->host_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get host health status
     */
    public function getHostHealth(ZabbixHost $host): array
    {
        try {
            $healthStatus = $host->getHealthStatus();

            return [
                'success' => true,
                'host_id' => $host->host_id,
                'host_name' => $host->host_name,
                'health_status' => $healthStatus,
            ];

        } catch (Exception $e) {
            Log::error('Failed to get host health', [
                'connection_id' => $this->connection->id,
                'host_id' => $host->host_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get hosts by status
     */
    public function getHostsByStatus(string $status): array
    {
        try {
            $hosts = $this->connection->hosts()
                ->byStatus($status)
                ->get();

            return [
                'success' => true,
                'status' => $status,
                'hosts_count' => $hosts->count(),
                'hosts' => $hosts,
            ];

        } catch (Exception $e) {
            Log::error('Failed to get hosts by status', [
                'connection_id' => $this->connection->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get hosts by availability
     */
    public function getHostsByAvailability(string $availability): array
    {
        try {
            $hosts = $this->connection->hosts()
                ->byAvailability($availability)
                ->get();

            return [
                'success' => true,
                'availability' => $availability,
                'hosts_count' => $hosts->count(),
                'hosts' => $hosts,
            ];

        } catch (Exception $e) {
            Log::error('Failed to get hosts by availability', [
                'connection_id' => $this->connection->id,
                'availability' => $availability,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get host statistics
     */
    public function getHostStats(): array
    {
        try {
            $hosts = $this->connection->hosts();

            return [
                'total_hosts' => $hosts->count(),
                'enabled_hosts' => $hosts->enabled()->count(),
                'disabled_hosts' => $hosts->byStatus('disabled')->count(),
                'maintenance_hosts' => $hosts->byStatus('maintenance')->count(),
                'available_hosts' => $hosts->available()->count(),
                'unavailable_hosts' => $hosts->byAvailability('unavailable')->count(),
                'unknown_hosts' => $hosts->byAvailability('unknown')->count(),
                'healthy_hosts' => $hosts->get()->filter(fn ($host) => $host->isHealthy())->count(),
            ];

        } catch (Exception $e) {
            Log::error('Failed to get host stats', [
                'connection_id' => $this->connection->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Validate host data
     */
    private function validateHostData(array $hostData): void
    {
        $required = ['host'];
        $missing = [];

        foreach ($required as $field) {
            if (! isset($hostData[$field]) || empty($hostData[$field])) {
                $missing[] = $field;
            }
        }

        if (! empty($missing)) {
            throw new Exception('Missing required fields: '.implode(', ', $missing));
        }

        // Validate host name format
        if (! preg_match('/^[a-zA-Z0-9._-]+$/', $hostData['host'])) {
            throw new Exception('Invalid host name format. Only alphanumeric characters, dots, underscores, and hyphens are allowed.');
        }

        // Validate IP address if provided
        if (isset($hostData['ip']) && ! empty($hostData['ip'])) {
            if (! filter_var($hostData['ip'], FILTER_VALIDATE_IP)) {
                throw new Exception('Invalid IP address format.');
            }
        }
    }

    /**
     * Generate host data from template
     */
    public function generateHostDataFromTemplate(array $templateData): array
    {
        return [
            'host' => $templateData['host_name'] ?? '',
            'name' => $templateData['visible_name'] ?? $templateData['host_name'] ?? '',
            'ip' => $templateData['ip_address'] ?? null,
            'templates' => $templateData['templates'] ?? [],
            'groups' => $templateData['groups'] ?? [],
            'interfaces' => $templateData['interfaces'] ?? [],
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
