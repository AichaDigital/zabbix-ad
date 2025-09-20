<?php

namespace App\Services\Zabbix;

use App\Models\ZabbixConnection;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class McpZabbixClient
{
    private ZabbixConnection $connection;

    private string $mcpServerUrl;

    private array $defaultHeaders;

    public function __construct(ZabbixConnection $connection)
    {
        $this->connection = $connection;
        $this->mcpServerUrl = config('zabbix.mcp_server_url', 'http://localhost:3000');
        $this->defaultHeaders = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Test connection to Zabbix server
     */
    public function testConnection(): array
    {
        try {
            $response = $this->makeMcpRequest('test_connection', [
                'url' => $this->connection->url,
                'token' => $this->connection->token,
                'timeout' => $this->connection->timeout_seconds,
            ]);

            return [
                'success' => true,
                'status' => 'active',
                'response_time' => $response['response_time'] ?? null,
                'zabbix_version' => $response['zabbix_version'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error('Zabbix connection test failed', [
                'connection_id' => $this->connection->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get all templates from Zabbix
     */
    public function getTemplates(): array
    {
        try {
            $response = $this->makeMcpRequest('get_templates', [
                'url' => $this->connection->url,
                'token' => $this->connection->token,
            ]);

            return $response['templates'] ?? [];
        } catch (Exception $e) {
            Log::error('Failed to get templates', [
                'connection_id' => $this->connection->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get all hosts from Zabbix
     */
    public function getHosts(): array
    {
        try {
            $response = $this->makeMcpRequest('get_hosts', [
                'url' => $this->connection->url,
                'token' => $this->connection->token,
            ]);

            return $response['hosts'] ?? [];
        } catch (Exception $e) {
            Log::error('Failed to get hosts', [
                'connection_id' => $this->connection->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Analyze template history trends
     */
    public function analyzeTemplateHistoryTrends(string $templateId): array
    {
        try {
            $response = $this->makeMcpRequest('analyze_template_history_trends', [
                'url' => $this->connection->url,
                'token' => $this->connection->token,
                'template_id' => $templateId,
            ]);

            return $response;
        } catch (Exception $e) {
            Log::error('Failed to analyze template history trends', [
                'connection_id' => $this->connection->id,
                'template_id' => $templateId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update template history trends
     */
    public function updateTemplateHistoryTrends(string $templateId, array $optimizationSettings): array
    {
        try {
            $response = $this->makeMcpRequest('update_template_history_trends', [
                'url' => $this->connection->url,
                'token' => $this->connection->token,
                'template_id' => $templateId,
                'optimization_settings' => $optimizationSettings,
            ]);

            return $response;
        } catch (Exception $e) {
            Log::error('Failed to update template history trends', [
                'connection_id' => $this->connection->id,
                'template_id' => $templateId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update all templates history trends automatically
     */
    public function updateAllTemplateHistoryTrendsAuto(): array
    {
        try {
            $response = $this->makeMcpRequest('update_all_template_history_trends_auto', [
                'url' => $this->connection->url,
                'token' => $this->connection->token,
            ]);

            return $response;
        } catch (Exception $e) {
            Log::error('Failed to update all templates history trends', [
                'connection_id' => $this->connection->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create a new host in Zabbix
     */
    /**
     * @param  array<string, mixed>  $hostData
     * @return array<string, mixed>
     */
    public function createHost(array $hostData): array
    {
        try {
            $response = $this->makeMcpRequest('create_host', [
                'url' => $this->connection->url,
                'token' => $this->connection->token,
                'host_data' => $hostData,
            ]);

            return $response;
        } catch (Exception $e) {
            Log::error('Failed to create host', [
                'connection_id' => $this->connection->id,
                'host_data' => $hostData,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create a new template in Zabbix
     */
    /**
     * @param  array<string, mixed>  $templateData
     * @return array<string, mixed>
     */
    public function createTemplate(array $templateData): array
    {
        try {
            $response = $this->makeMcpRequest('create_template', [
                'url' => $this->connection->url,
                'token' => $this->connection->token,
                'template_data' => $templateData,
            ]);

            return $response;
        } catch (Exception $e) {
            Log::error('Failed to create template', [
                'connection_id' => $this->connection->id,
                'template_data' => $templateData,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Make a request to the MCP server
     */
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function makeMcpRequest(string $method, array $params = []): array
    {
        $payload = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => uniqid(),
        ];

        $response = Http::timeout($this->connection->timeout_seconds)
            ->withHeaders($this->defaultHeaders)
            ->post($this->mcpServerUrl, $payload);

        if (! $response->successful()) {
            throw new Exception("MCP server request failed: {$response->status()} - {$response->body()}");
        }

        $data = $response->json();

        if (is_array($data) && isset($data['error'])) {
            $errorMessage = is_array($data['error']) && isset($data['error']['message'])
                ? (string) $data['error']['message']
                : 'Unknown error';
            throw new Exception("MCP server error: {$errorMessage}");
        }

        return is_array($data) && isset($data['result']) ? $data['result'] : [];
    }

    /**
     * Get connection statistics
     */
    /**
     * @return array<string, mixed>
     */
    public function getConnectionStats(): array
    {
        try {
            $templates = $this->getTemplates();
            $hosts = $this->getHosts();

            return [
                'templates_count' => count($templates),
                'hosts_count' => count($hosts),
                'last_check' => now(),
                'connection_status' => 'active',
            ];
        } catch (Exception $e) {
            return [
                'templates_count' => 0,
                'hosts_count' => 0,
                'last_check' => now(),
                'connection_status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }
}
