<?php

namespace App\Filament\Admin\Widgets;

use App\Models\ZabbixConnection;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ConnectionsStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalConnections = ZabbixConnection::count();
        $activeConnections = ZabbixConnection::where('is_active', true)->count();
        $inactiveConnections = ZabbixConnection::where('is_active', false)->count();

        return [
            Stat::make('Total Connections', $totalConnections)
                ->description('All Zabbix connections')
                ->descriptionIcon('heroicon-m-server')
                ->color('primary'),

            Stat::make('Active Connections', $activeConnections)
                ->description('Currently active')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Inactive Connections', $inactiveConnections)
                ->description('Currently disabled')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
