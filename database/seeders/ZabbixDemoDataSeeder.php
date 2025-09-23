<?php

namespace Database\Seeders;

use App\Models\BackgroundJob;
use App\Models\ZabbixConnection;
use App\Models\ZabbixHost;
use App\Models\ZabbixTemplate;
use Illuminate\Database\Seeder;

class ZabbixDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo Zabbix connections
        $connections = [
            [
                'name' => 'Production Zabbix',
                'description' => 'Main production monitoring server',
                'url' => 'https://zabbix-prod.company.com',
                'encrypted_token' => 'demo_token_prod_123',
                'environment' => 'production',
                'is_active' => true,
                'max_requests_per_minute' => 1000,
                'timeout_seconds' => 30,
                'connection_status' => 'active',
            ],
            [
                'name' => 'Staging Zabbix',
                'description' => 'Staging environment monitoring',
                'url' => 'https://zabbix-staging.company.com',
                'encrypted_token' => 'demo_token_staging_456',
                'environment' => 'staging',
                'is_active' => true,
                'max_requests_per_minute' => 500,
                'timeout_seconds' => 30,
                'connection_status' => 'active',
            ],
            [
                'name' => 'Development Zabbix',
                'description' => 'Development environment monitoring',
                'url' => 'https://zabbix-dev.company.com',
                'encrypted_token' => 'demo_token_dev_789',
                'environment' => 'local',
                'is_active' => false,
                'max_requests_per_minute' => 100,
                'timeout_seconds' => 15,
                'connection_status' => 'inactive',
            ],
        ];

        foreach ($connections as $connectionData) {
            $connection = ZabbixConnection::create($connectionData);

            // Create demo hosts for each connection
            $hosts = [
                [
                    'host_id' => '10001',
                    'host_name' => 'web01.company.com',
                    'visible_name' => 'Web Server 01',
                    'ip_address' => '192.168.1.10',
                    'status' => 'enabled',
                    'available' => 'available',
                    'templates_count' => 2,
                    'items_count' => 45,
                ],
                [
                    'host_id' => '10002',
                    'host_name' => 'db01.company.com',
                    'visible_name' => 'Database Server 01',
                    'ip_address' => '192.168.1.11',
                    'status' => 'enabled',
                    'available' => 'available',
                    'templates_count' => 1,
                    'items_count' => 32,
                ],
                [
                    'host_id' => '10003',
                    'host_name' => 'lb01.company.com',
                    'visible_name' => 'Load Balancer 01',
                    'ip_address' => '192.168.1.12',
                    'status' => 'enabled',
                    'available' => 'unavailable',
                    'templates_count' => 1,
                    'items_count' => 28,
                ],
            ];

            foreach ($hosts as $hostData) {
                $hostData['zabbix_connection_id'] = $connection->id;
                ZabbixHost::create($hostData);
            }

            // Create demo templates for each connection
            $templates = [
                [
                    'name' => 'Linux Server Template',
                    'template_id' => '10001',
                    'description' => 'Standard Linux server monitoring template',
                    'template_type' => 'system',
                    'items_count' => 45,
                    'triggers_count' => 12,
                    'history_retention' => '30d',
                    'trends_retention' => '365d',
                    'is_optimized' => true,
                ],
                [
                    'name' => 'MySQL Database Template',
                    'template_id' => '10002',
                    'description' => 'MySQL database monitoring template',
                    'template_type' => 'custom',
                    'items_count' => 32,
                    'triggers_count' => 8,
                    'history_retention' => '7d',
                    'trends_retention' => '90d',
                    'is_optimized' => false,
                ],
                [
                    'name' => 'Network Device Template',
                    'template_id' => '10003',
                    'description' => 'Network device monitoring template',
                    'template_type' => 'imported',
                    'items_count' => 28,
                    'triggers_count' => 6,
                    'history_retention' => '7d',
                    'trends_retention' => '180d',
                    'is_optimized' => true,
                ],
            ];

            foreach ($templates as $templateData) {
                $templateData['zabbix_connection_id'] = $connection->id;
                ZabbixTemplate::create($templateData);
            }

            // Create demo background jobs
            $jobs = [
                [
                    'job_type' => 'sync_templates',
                    'status' => 'completed',
                    'progress_percentage' => 100,
                    'started_at' => now()->subHours(2),
                    'completed_at' => now()->subHours(1),
                ],
                [
                    'job_type' => 'sync_hosts',
                    'status' => 'running',
                    'progress_percentage' => 65,
                    'started_at' => now()->subMinutes(30),
                ],
                [
                    'job_type' => 'optimize_templates',
                    'status' => 'failed',
                    'progress_percentage' => 25,
                    'started_at' => now()->subHours(4),
                    'error_message' => 'Connection timeout to Zabbix server',
                ],
            ];

            foreach ($jobs as $jobData) {
                $jobData['zabbix_connection_id'] = $connection->id;
                BackgroundJob::create($jobData);
            }
        }
    }
}
