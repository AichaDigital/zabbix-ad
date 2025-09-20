<?php

namespace App\Filament\Admin\Resources\ZabbixHosts\Pages;

use App\Filament\Admin\Resources\ZabbixHosts\ZabbixHostResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditZabbixHost extends EditRecord
{
    protected static string $resource = ZabbixHostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
