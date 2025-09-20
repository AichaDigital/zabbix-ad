<?php

namespace App\Console\Commands\Connection;

use App\Models\ZabbixConnection;
use App\Services\Zabbix\McpZabbixClient;
use Illuminate\Console\Command;

class TestConnectionCommand extends Command
{
    protected $signature = 'zabbix:connection:test
                            {connection? : ID or name of the connection to test}
                            {--all : Test all connections}';

    protected $description = 'Test Zabbix connection(s)';

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->testAllConnections();
        }

        $connection = $this->getConnection();
        if (! $connection) {
            return self::FAILURE;
        }

        return $this->testConnection($connection);
    }

    private function testAllConnections(): int
    {
        $this->info('Testing all Zabbix connections...');

        $connections = ZabbixConnection::active()->get();

        if ($connections->isEmpty()) {
            $this->warn('No active connections found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Connection', 'URL', 'Environment', 'Status', 'Response Time', 'Zabbix Version'],
            []
        );

        $successCount = 0;
        $totalCount = $connections->count();

        $tableData = [];
        foreach ($connections as $connection) {
            $result = $this->testConnectionSilent($connection);

            $tableData[] = [
                $connection->name,
                $connection->url,
                $connection->environment,
                $result['success'] ? '<fg=green>✓ Active</>' : '<fg=red>✗ Error</>',
                $result['response_time'] ?? 'N/A',
                $result['zabbix_version'] ?? 'N/A',
            ];

            if ($result['success']) {
                $successCount++;
            }
        }

        $this->table(
            ['Connection', 'URL', 'Environment', 'Status', 'Response Time', 'Zabbix Version'],
            $tableData
        );

        $this->newLine();
        $this->info("Test completed: {$successCount}/{$totalCount} connections successful");

        return $successCount === $totalCount ? self::SUCCESS : self::FAILURE;
    }

    private function testConnection(ZabbixConnection $connection): int
    {
        $this->info("Testing connection: {$connection->name}");
        $this->line("URL: {$connection->url}");
        $this->line("Environment: {$connection->environment}");
        $this->newLine();

        $this->info('Testing connection...');
        $bar = $this->output->createProgressBar(1);
        $bar->start();

        $client = app(McpZabbixClient::class, ['connection' => $connection]);
        $result = $client->testConnection();

        $bar->finish();
        $this->newLine(2);

        if ($result['success']) {
            $this->info('✓ Connection successful!');
            $this->line("Status: {$result['status']}");
            $this->line("Response Time: {$result['response_time']}ms");
            $this->line("Zabbix Version: {$result['zabbix_version']}");

            // Update connection status
            $connection->update([
                'last_connection_test' => now(),
                'connection_status' => $result['status'],
            ]);

            return self::SUCCESS;
        } else {
            $this->error('✗ Connection failed!');
            $this->line("Error: {$result['error']}");

            // Update connection status
            $connection->update([
                'last_connection_test' => now(),
                'connection_status' => 'error',
            ]);

            return self::FAILURE;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function testConnectionSilent(ZabbixConnection $connection): array
    {
        try {
            $client = app(McpZabbixClient::class, ['connection' => $connection]);
            $result = $client->testConnection();

            // Update connection status
            $connection->update([
                'last_connection_test' => now(),
                'connection_status' => $result['success'] ? 'active' : 'error',
            ]);

            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function getConnection(): ?ZabbixConnection
    {
        $identifier = $this->argument('connection');

        if (! $identifier) {
            $connections = ZabbixConnection::all();

            if ($connections->isEmpty()) {
                $this->error('No connections found.');

                return null;
            }

            $connectionName = $this->choice(
                'Select a connection to test:',
                $connections->pluck('name')->toArray()
            );

            return $connections->firstWhere('name', $connectionName);
        }

        // Try to find by ID first, then by name
        $connection = ZabbixConnection::find($identifier)
            ?? ZabbixConnection::where('name', $identifier)->first();

        if (! $connection) {
            $this->error("Connection not found: {$identifier}");

            return null;
        }

        return $connection;
    }
}
