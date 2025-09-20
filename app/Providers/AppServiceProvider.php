<?php

namespace App\Providers;

use App\Services\Zabbix\McpZabbixClient;
use App\Services\Zabbix\ZabbixSyncService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Zabbix services
        $this->app->bind(McpZabbixClient::class, function ($app, $parameters) {
            return new McpZabbixClient($parameters['connection'] ?? null);
        });

        $this->app->bind(ZabbixSyncService::class, function ($app, $parameters) {
            return new ZabbixSyncService($parameters['connection'] ?? null);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
