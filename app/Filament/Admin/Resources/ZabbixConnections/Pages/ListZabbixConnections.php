<?php

namespace App\Filament\Admin\Resources\ZabbixConnections\Pages;

use App\Filament\Admin\Resources\ZabbixConnections\ZabbixConnectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListZabbixConnections extends ListRecords
{
    protected static string $resource = ZabbixConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
