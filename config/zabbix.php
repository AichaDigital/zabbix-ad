<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Zabbix MCP Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the MCP Zabbix server integration
    |
    */

    'mcp_server_url' => env('ZABBIX_MCP_SERVER_URL', 'http://localhost:3000'),

    /*
    |--------------------------------------------------------------------------
    | Default Optimization Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for template optimization
    |
    */

    'default_optimization' => [
        'history_retention' => '7d',
        'trends_retention' => '30d',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for data synchronization
    |
    */

    'sync' => [
        'batch_size' => env('ZABBIX_SYNC_BATCH_SIZE', 100),
        'timeout' => env('ZABBIX_SYNC_TIMEOUT', 300),
        'retry_attempts' => env('ZABBIX_SYNC_RETRY_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Connection Settings
    |--------------------------------------------------------------------------
    |
    | Default connection settings for Zabbix servers
    |
    */

    'connection' => [
        'default_timeout' => 30,
        'default_max_requests_per_minute' => 60,
        'default_environment' => 'production',
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Optimization Rules
    |--------------------------------------------------------------------------
    |
    | Default optimization rules for different environments
    |
    */

    'optimization_rules' => [
        'local' => [
            'history_retention' => '3d',
            'trends_retention' => '7d',
        ],
        'staging' => [
            'history_retention' => '5d',
            'trends_retention' => '14d',
        ],
        'production' => [
            'history_retention' => '7d',
            'trends_retention' => '30d',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Configuration for audit logging
    |
    */

    'audit' => [
        'enabled' => env('ZABBIX_AUDIT_ENABLED', true),
        'log_level' => env('ZABBIX_AUDIT_LOG_LEVEL', 'info'),
        'retention_days' => env('ZABBIX_AUDIT_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Background Jobs
    |--------------------------------------------------------------------------
    |
    | Configuration for background job processing
    |
    */

    'jobs' => [
        'queue' => env('ZABBIX_JOBS_QUEUE', 'default'),
        'timeout' => env('ZABBIX_JOBS_TIMEOUT', 600),
        'retry_after' => env('ZABBIX_JOBS_RETRY_AFTER', 60),
        'max_tries' => env('ZABBIX_JOBS_MAX_TRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check
    |--------------------------------------------------------------------------
    |
    | Configuration for health check monitoring
    |
    */

    'health_check' => [
        'enabled' => env('ZABBIX_HEALTH_CHECK_ENABLED', true),
        'interval' => env('ZABBIX_HEALTH_CHECK_INTERVAL', 300), // 5 minutes
        'timeout' => env('ZABBIX_HEALTH_CHECK_TIMEOUT', 30),
    ],
];
