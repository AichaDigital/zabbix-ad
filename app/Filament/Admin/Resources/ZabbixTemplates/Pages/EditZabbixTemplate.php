<?php

namespace App\Filament\Admin\Resources\ZabbixTemplates\Pages;

use App\Filament\Admin\Resources\ZabbixTemplates\ZabbixTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditZabbixTemplate extends EditRecord
{
    protected static string $resource = ZabbixTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
