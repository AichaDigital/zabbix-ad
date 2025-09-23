<?php

namespace App\Filament\Admin\Widgets;

use App\Models\ZabbixTemplate;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TemplatesStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalTemplates = ZabbixTemplate::count();
        $customTemplates = ZabbixTemplate::where('template_type', 'custom')->count();
        $optimizedTemplates = ZabbixTemplate::where('is_optimized', true)->count();

        return [
            Stat::make('Total Templates', $totalTemplates)
                ->description('All monitoring templates')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),

            Stat::make('Custom Templates', $customTemplates)
                ->description('User-created templates')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color('warning'),

            Stat::make('Optimized Templates', $optimizedTemplates)
                ->description('Performance optimized')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('success'),
        ];
    }
}
