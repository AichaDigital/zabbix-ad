<?php

namespace App\Filament\Admin\Resources\BackgroundJobs\Pages;

use App\Filament\Admin\Resources\BackgroundJobs\BackgroundJobResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBackgroundJob extends EditRecord
{
    protected static string $resource = BackgroundJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
