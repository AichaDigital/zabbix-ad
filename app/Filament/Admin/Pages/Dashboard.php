<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\BackgroundJobsStatsWidget;
use App\Filament\Admin\Widgets\ConnectionsStatsWidget;
use App\Filament\Admin\Widgets\HostsStatsWidget;
use App\Filament\Admin\Widgets\RecentConnectionsWidget;
use App\Filament\Admin\Widgets\TemplatesStatsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected function getHeaderWidgets(): array
    {
        return [
            ConnectionsStatsWidget::class,
            HostsStatsWidget::class,
            TemplatesStatsWidget::class,
            BackgroundJobsStatsWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            RecentConnectionsWidget::class,
        ];
    }
}
