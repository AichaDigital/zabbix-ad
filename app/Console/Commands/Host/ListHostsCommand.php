<?php

namespace App\Console\Commands\Host;

use App\Models\ZabbixConnection;
use App\Models\ZabbixHost;
use Illuminate\Console\Command;

class ListHostsCommand extends Command
{
    protected $signature = 'zabbix:host:list
                            {connection? : ID or name of the connection}
                            {--status= : Filter by status (enabled, disabled, maintenance)}
                            {--available= : Filter by availability (available, unavailable, unknown)}
                            {--healthy : Show only healthy hosts}';

    protected $description = 'List Zabbix hosts';

    public function handle(): int
    {
        $connection = $this->getConnection();
        if (! $connection) {
            return self::FAILURE;
        }

        $query = ZabbixHost::where('zabbix_connection_id', $connection->id);

        // Apply filters
        if ($status = $this->option('status')) {
            $query->byStatus($status);
        }

        if ($available = $this->option('available')) {
            $query->byAvailability($available);
        }

        if ($this->option('healthy')) {
            $query->enabled()->available();
        }

        $hosts = $query->orderBy('host_name')->get();

        if ($hosts->isEmpty()) {
            $this->warn('No hosts found matching the criteria.');

            return self::SUCCESS;
        }

        $this->info("Hosts for connection: {$connection->name}");
        $this->line("Total: {$hosts->count()} hosts");
        $this->newLine();

        $headers = ['ID', 'Host Name', 'IP', 'Status', 'Available', 'Templates', 'Items', 'Last Check'];
        $rows = [];

        foreach ($hosts as $host) {
            $rows[] = [
                $host->host_id,
                $host->host_name,
                $host->ip_address ?? 'N/A',
                $this->formatStatus($host->status),
                $this->formatAvailability($host->available),
                $host->templates_count,
                $host->items_count,
                $host->last_check ? $host->last_check->format('Y-m-d H:i:s') : 'Never',
            ];
        }

        $this->table($headers, $rows);

        // Show summary
        $this->newLine();
        $this->showSummary($hosts);

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Models\ZabbixHost>  $hosts
     */
    private function showSummary(\Illuminate\Database\Eloquent\Collection $hosts): void
    {
        $summary = [
            'Total Hosts' => $hosts->count(),
            'Enabled Hosts' => $hosts->where('status', 'enabled')->count(),
            'Disabled Hosts' => $hosts->where('status', 'disabled')->count(),
            'Maintenance Hosts' => $hosts->where('status', 'maintenance')->count(),
            'Available Hosts' => $hosts->where('available', 'available')->count(),
            'Unavailable Hosts' => $hosts->where('available', 'unavailable')->count(),
            'Unknown Status' => $hosts->where('available', 'unknown')->count(),
            'Healthy Hosts' => $hosts->filter(fn ($host) => $host->available === 'available' && $host->status === 'enabled')->count(),
            'Total Templates' => $hosts->sum('templates_count'),
            'Total Items' => $hosts->sum('items_count'),
        ];

        $this->info('Summary:');
        foreach ($summary as $label => $value) {
            $this->line("  {$label}: ".(is_scalar($value) ? (string) $value : '0'));
        }
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            'enabled' => '<fg=green>Enabled</>',
            'disabled' => '<fg=red>Disabled</>',
            'maintenance' => '<fg=yellow>Maintenance</>',
            default => $status,
        };
    }

    private function formatAvailability(string $available): string
    {
        return match ($available) {
            'available' => '<fg=green>Available</>',
            'unavailable' => '<fg=red>Unavailable</>',
            'unknown' => '<fg=yellow>Unknown</>',
            default => $available,
        };
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
                'Select a connection:',
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
