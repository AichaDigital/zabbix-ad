<?php

namespace App\Filament\Admin\Resources\ZabbixTemplates\Pages;

use App\Filament\Admin\Resources\ZabbixTemplates\ZabbixTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListZabbixTemplates extends ListRecords
{
    protected static string $resource = ZabbixTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
