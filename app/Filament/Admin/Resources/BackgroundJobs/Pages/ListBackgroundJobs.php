<?php

namespace App\Filament\Admin\Resources\BackgroundJobs\Pages;

use App\Filament\Admin\Resources\BackgroundJobs\BackgroundJobResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBackgroundJobs extends ListRecords
{
    protected static string $resource = BackgroundJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
