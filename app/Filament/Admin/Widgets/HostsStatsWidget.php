<?php

namespace App\Filament\Admin\Widgets;

use App\Models\ZabbixHost;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HostsStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalHosts = ZabbixHost::count();
        $enabledHosts = ZabbixHost::where('status', 'enabled')->count();
        $availableHosts = ZabbixHost::where('available', 'available')->count();

        return [
            Stat::make('Total Hosts', $totalHosts)
                ->description('All monitored hosts')
                ->descriptionIcon('heroicon-m-computer-desktop')
                ->color('primary'),

            Stat::make('Enabled Hosts', $enabledHosts)
                ->description('Currently enabled')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Available Hosts', $availableHosts)
                ->description('Currently available')
                ->descriptionIcon('heroicon-m-signal')
                ->color('info'),
        ];
    }
}
