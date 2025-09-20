<?php

namespace App\Filament\Admin\Resources\ZabbixHosts\Pages;

use App\Filament\Admin\Resources\ZabbixHosts\ZabbixHostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListZabbixHosts extends ListRecords
{
    protected static string $resource = ZabbixHostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
