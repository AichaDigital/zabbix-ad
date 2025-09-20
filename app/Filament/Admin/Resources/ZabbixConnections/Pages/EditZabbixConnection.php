<?php

namespace App\Filament\Admin\Resources\ZabbixConnections\Pages;

use App\Filament\Admin\Resources\ZabbixConnections\ZabbixConnectionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditZabbixConnection extends EditRecord
{
    protected static string $resource = ZabbixConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
