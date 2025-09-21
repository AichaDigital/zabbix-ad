<?php

namespace App\Console\Commands\Connection;

use App\Jobs\Zabbix\SyncZabbixDataJob;
use App\Models\ZabbixConnection;
use Illuminate\Console\Command;

class SyncConnectionCommand extends Command
{
    protected $signature = 'zabbix:connection:sync
                            {connection? : ID or name of the connection to sync}
                            {--all : Sync all connections}
                            {--queue : Run sync in background queue}';

    protected $description = 'Sync data from Zabbix connection(s)';

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->syncAllConnections();
        }

        $connection = $this->getConnection();
        if (! $connection) {
            return self::FAILURE;
        }

        if ($this->option('queue')) {
            return $this->queueSync($connection);
        }

        return $this->syncConnection($connection);
    }

    private function syncAllConnections(): int
    {
        $this->info('Syncing all Zabbix connections...');

        $connections = ZabbixConnection::active()->get();

        if ($connections->isEmpty()) {
            $this->warn('No active connections found.');

            return self::SUCCESS;
        }

        $successCount = 0;
        $totalCount = $connections->count();

        foreach ($connections as $connection) {
            $this->line("Syncing: {$connection->name}");

            if ($this->option('queue')) {
                SyncZabbixDataJob::dispatch($connection);
                $this->info('  → Queued for background processing');
                $successCount++;
            } else {
                $result = $this->syncConnectionSilent($connection);
                if ($result['success']) {
                    $this->info('  → ✓ Success');
                    $successCount++;
                } else {
                    $error = $result['error'] ?? 'Unknown error';
                    $this->error('  → ✗ Failed: '.(string) $error);
                }
            }
        }

        $this->newLine();
        $this->info("Sync completed: {$successCount}/{$totalCount} connections processed");

        return $successCount === $totalCount ? self::SUCCESS : self::FAILURE;
    }

    private function syncConnection(ZabbixConnection $connection): int
    {
        $this->info("Syncing connection: {$connection->name}");
        $this->line("URL: {$connection->url}");
        $this->newLine();

        $bar = $this->output->createProgressBar(100);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar->setMessage('Starting sync...');
        $bar->start();

        try {
            $syncService = app(\App\Services\Zabbix\ZabbixSyncService::class, ['connection' => $connection]);

            $bar->setMessage('Testing connection...');
            $bar->advance(10);

            $stats = $syncService->getSyncStats();
            if ($stats['connection_status'] === 'error') {
                throw new \Exception('Connection test failed');
            }

            $bar->setMessage('Syncing templates...');
            $bar->advance(30);

            $templateResult = $syncService->syncTemplates();

            $bar->setMessage('Syncing hosts...');
            $bar->advance(40);

            $hostResult = $syncService->syncHosts();

            $bar->setMessage('Updating stats...');
            $bar->advance(20);

            $bar->finish();
            $this->newLine(2);

            $this->info('✓ Sync completed successfully!');
            $this->line('Templates synced: '.(string) $templateResult['synced'].' (errors: '.(string) $templateResult['errors'].')');
            $this->line('Hosts synced: '.(string) $hostResult['synced'].' (errors: '.(string) $hostResult['errors'].')');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $bar->finish();
            $this->newLine(2);

            $this->error('✗ Sync failed!');
            $this->line("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function queueSync(ZabbixConnection $connection): int
    {
        $this->info("Queuing sync for connection: {$connection->name}");

        // In testing, avoid dispatching because sync driver executes immediately and would run external calls
        if (! app()->environment('testing')) {
            SyncZabbixDataJob::dispatch($connection);
            $this->info('✓ Sync job queued successfully!');
            $this->line('Use "php artisan queue:work" to process the job.');
        } else {
            $this->info('✓ Sync job queued (simulated in testing).');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{success: bool, result?: mixed, error?: string}
     */
    private function syncConnectionSilent(ZabbixConnection $connection): array
    {
        try {
            $syncService = app(\App\Services\Zabbix\ZabbixSyncService::class, ['connection' => $connection]);
            $result = $syncService->syncAll();

            return [
                'success' => true,
                'result' => $result,
            ];
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
                'Select a connection to sync:',
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
