<?php

namespace App\Console\Commands\Host;

use App\Jobs\Zabbix\CreateHostsJob;
use App\Models\ZabbixConnection;
use App\Services\Zabbix\HostManagementService;
use Illuminate\Console\Command;

class CreateHostsCommand extends Command
{
    protected $signature = 'zabbix:host:create
                            {connection : ID or name of the connection}
                            {--file= : JSON file containing host data}
                            {--interactive : Create hosts interactively}
                            {--queue : Run creation in background queue}';

    protected $description = 'Create Zabbix hosts';

    public function handle(): int
    {
        $connection = $this->getConnection();
        if (! $connection) {
            return self::FAILURE;
        }

        if ($this->option('file')) {
            return $this->createFromFile($connection);
        }

        if ($this->option('interactive')) {
            return $this->createInteractively($connection);
        }

        $this->error('Please specify --file or --interactive option.');

        return self::FAILURE;
    }

    private function createFromFile(ZabbixConnection $connection): int
    {
        $filePath = $this->option('file');

        if (! $filePath || ! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            $this->error('Could not read file: '.$filePath);

            return self::FAILURE;
        }

        $hostsData = json_decode($content, true);
        if ($hostsData === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON file: '.json_last_error_msg());

            return self::FAILURE;
        }

        if (! is_array($hostsData) || ! array_is_list($hostsData)) {
            $this->error('JSON file must contain an array of host data.');

            return self::FAILURE;
        }

        $this->info("Creating {$connection->name} hosts from file: {$filePath}");
        $this->line('Found '.count($hostsData).' hosts to create');
        $this->newLine();

        if ($this->option('queue')) {
            CreateHostsJob::dispatch($connection, $hostsData);
            $this->info('✓ Host creation job queued successfully!');

            return self::SUCCESS;
        }

        return $this->createHosts($connection, $hostsData);
    }

    private function createInteractively(ZabbixConnection $connection): int
    {
        $this->info("Creating hosts interactively for connection: {$connection->name}");
        $this->newLine();

        $hostsData = [];
        $continue = true;

        while ($continue) {
            $hostData = $this->collectHostData();
            $hostsData[] = $hostData;

            $this->newLine();
            $continue = $this->confirm('Add another host?');
        }

        $this->info('Creating '.count($hostsData).' hosts...');

        if ($this->option('queue')) {
            CreateHostsJob::dispatch($connection, $hostsData);
            $this->info('✓ Host creation job queued successfully!');

            return self::SUCCESS;
        }

        return $this->createHosts($connection, $hostsData);
    }

    /**
     * @param  list<array<string, mixed>>  $hostsData
     */
    private function createHosts(ZabbixConnection $connection, array $hostsData): int
    {
        try {
            $hostService = new HostManagementService($connection);

            $bar = $this->output->createProgressBar(count($hostsData));
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
            $bar->setMessage('Creating hosts...');
            $bar->start();

            $result = $hostService->createHostsBatch($hostsData);

            $bar->finish();
            $this->newLine(2);

            $this->info('✓ Host creation completed!');
            $this->line("Created: {$result['results']['created']}");
            $this->line("Errors: {$result['results']['errors']}");
            $this->line('Total: '.count($hostsData));

            // Show detailed results
            if ($result['results']['errors'] > 0) {
                $this->newLine();
                $this->warn('Hosts with errors:');
                foreach ($result['results']['hosts'] as $host) {
                    if ($host['status'] === 'error') {
                        $this->line("  - {$host['host_name']}: {$host['error']}");
                    }
                }
            }

            return $result['results']['errors'] === 0 ? self::SUCCESS : self::FAILURE;

        } catch (\Exception $e) {
            $this->error('✗ Host creation failed!');
            $this->line("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function collectHostData(): array
    {
        $this->line('<fg=cyan>Host Information:</>');

        $hostData = [
            'host' => $this->ask('Host name (required)'),
            'name' => $this->ask('Visible name', ''),
            'ip' => $this->ask('IP address', ''),
        ];

        // Validate required fields
        if (empty($hostData['host'])) {
            $this->error('Host name is required.');

            return $this->collectHostData();
        }

        // Ask for templates
        $this->line('<fg=cyan>Templates (optional):</>');
        $templates = [];
        $addTemplate = $this->confirm('Add templates?', false);

        while ($addTemplate) {
            $templateName = $this->ask('Template name');
            if ($templateName) {
                $templates[] = ['name' => $templateName];
            }
            $addTemplate = $this->confirm('Add another template?', false);
        }

        $hostData['templates'] = $templates;

        // Ask for groups
        $this->line('<fg=cyan>Groups (optional):</>');
        $groups = [];
        $addGroup = $this->confirm('Add groups?', false);

        while ($addGroup) {
            $groupName = $this->ask('Group name');
            if ($groupName) {
                $groups[] = ['name' => $groupName];
            }
            $addGroup = $this->confirm('Add another group?', false);
        }

        $hostData['groups'] = $groups;

        return $hostData;
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
}
