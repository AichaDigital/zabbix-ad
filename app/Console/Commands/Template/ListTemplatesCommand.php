<?php

namespace App\Console\Commands\Template;

use App\Models\ZabbixConnection;
use App\Models\ZabbixTemplate;
use Illuminate\Console\Command;

class ListTemplatesCommand extends Command
{
    protected $signature = 'zabbix:template:list
                            {connection? : ID or name of the connection}
                            {--type= : Filter by template type (system, custom, imported)}
                            {--optimized : Show only optimized templates}
                            {--needs-optimization : Show only templates needing optimization}';

    protected $description = 'List Zabbix templates';

    public function handle(): int
    {
        $connection = $this->getConnection();
        if (! $connection) {
            return self::FAILURE;
        }

        $query = ZabbixTemplate::where('zabbix_connection_id', $connection->id);

        // Apply filters
        if ($type = $this->option('type')) {
            $query->byType($type);
        }

        if ($this->option('optimized')) {
            $query->optimized();
        }

        if ($this->option('needs-optimization')) {
            $query->needsOptimization();
        }

        $templates = $query->orderBy('name')->get();

        if ($templates->isEmpty()) {
            $this->warn('No templates found matching the criteria.');

            return self::SUCCESS;
        }

        $this->info("Templates for connection: {$connection->name}");
        $this->line("Total: {$templates->count()} templates");
        $this->newLine();

        $headers = ['ID', 'Name', 'Type', 'Items', 'Triggers', 'History', 'Trends', 'Optimized'];
        $rows = [];

        foreach ($templates as $template) {
            $rows[] = [
                $template->template_id,
                $template->name,
                $this->formatTemplateType($template->template_type),
                $template->items_count,
                $template->triggers_count,
                $template->history_retention,
                $template->trends_retention,
                $template->is_optimized ? '<fg=green>✓</>' : '<fg=red>✗</>',
            ];
        }

        $this->table($headers, $rows);

        // Show summary
        $this->newLine();
        $this->showSummary($templates);

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Models\ZabbixTemplate>  $templates
     */
    private function showSummary(\Illuminate\Database\Eloquent\Collection $templates): void
    {
        $summary = [
            'Total Templates' => $templates->count(),
            'System Templates' => $templates->where('template_type', 'system')->count(),
            'Custom Templates' => $templates->where('template_type', 'custom')->count(),
            'Imported Templates' => $templates->where('template_type', 'imported')->count(),
            'Optimized Templates' => $templates->where('is_optimized', true)->count(),
            'Needs Optimization' => $templates->where('is_optimized', false)->count(),
            'Total Items' => $templates->sum('items_count'),
            'Total Triggers' => $templates->sum('triggers_count'),
        ];

        $this->info('Summary:');
        foreach ($summary as $label => $value) {
            $this->line("  {$label}: ".(is_scalar($value) ? (string) $value : '0'));
        }
    }

    private function formatTemplateType(string $type): string
    {
        return match ($type) {
            'system' => '<fg=blue>System</>',
            'custom' => '<fg=green>Custom</>',
            'imported' => '<fg=yellow>Imported</>',
            default => $type,
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
