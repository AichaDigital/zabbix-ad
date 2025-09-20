<?php

dataset('zabbix-connection-data', [
    'local connection' => [
        'name' => 'Local Zabbix',
        'url' => 'http://localhost:8080',
        'environment' => 'local',
        'is_active' => true,
    ],
    'production connection' => [
        'name' => 'Production Zabbix',
        'url' => 'https://zabbix.company.com',
        'environment' => 'production',
        'is_active' => true,
    ],
    'staging connection' => [
        'name' => 'Staging Zabbix',
        'url' => 'https://staging.zabbix.company.com',
        'environment' => 'staging',
        'is_active' => false,
    ],
]);

dataset('zabbix-template-data', [
    'system template' => [
        'template_type' => 'system',
        'name' => 'Linux Server',
        'is_optimized' => false,
    ],
    'custom template' => [
        'template_type' => 'custom',
        'name' => 'Custom Application',
        'is_optimized' => true,
    ],
    'imported template' => [
        'template_type' => 'imported',
        'name' => 'Imported Template',
        'is_optimized' => false,
    ],
]);

dataset('zabbix-host-data', [
    'enabled host' => [
        'status' => 'enabled',
        'available' => 'available',
        'host_name' => 'Test Server 1',
    ],
    'disabled host' => [
        'status' => 'disabled',
        'available' => 'unavailable',
        'host_name' => 'Test Server 2',
    ],
    'maintenance host' => [
        'status' => 'maintenance',
        'available' => 'unknown',
        'host_name' => 'Test Server 3',
    ],
]);

dataset('background-job-data', [
    'sync job' => [
        'job_type' => 'sync_templates',
        'status' => 'pending',
    ],
    'optimize job' => [
        'job_type' => 'optimize_templates',
        'status' => 'running',
    ],
    'create job' => [
        'job_type' => 'create_hosts',
        'status' => 'completed',
    ],
]);

dataset('audit-log-data', [
    'success log' => [
        'action' => 'create_host',
        'resource_type' => 'host',
        'status' => 'success',
    ],
    'error log' => [
        'action' => 'update_template',
        'resource_type' => 'template',
        'status' => 'error',
    ],
    'info log' => [
        'action' => 'sync_connection',
        'resource_type' => 'connection',
        'status' => 'success',
    ],
]);
