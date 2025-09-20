<?php

namespace App\Filament\Admin\Resources\ZabbixHosts\Pages;

use App\Filament\Admin\Resources\ZabbixHosts\ZabbixHostResource;
use Filament\Resources\Pages\CreateRecord;

class CreateZabbixHost extends CreateRecord
{
    protected static string $resource = ZabbixHostResource::class;
}
