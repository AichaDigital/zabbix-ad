<?php

namespace App\Filament\Admin\Widgets;

use App\Models\BackgroundJob;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BackgroundJobsStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalJobs = BackgroundJob::count();
        $runningJobs = BackgroundJob::where('status', 'running')->count();
        $completedJobs = BackgroundJob::where('status', 'completed')->count();
        $failedJobs = BackgroundJob::where('status', 'failed')->count();

        return [
            Stat::make('Total Jobs', $totalJobs)
                ->description('All background jobs')
                ->descriptionIcon('heroicon-m-cog-6-tooth')
                ->color('primary'),

            Stat::make('Running Jobs', $runningJobs)
                ->description('Currently executing')
                ->descriptionIcon('heroicon-m-play')
                ->color('warning'),

            Stat::make('Completed Jobs', $completedJobs)
                ->description('Successfully finished')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Failed Jobs', $failedJobs)
                ->description('Failed execution')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
