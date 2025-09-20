<?php

namespace App\Filament\Admin\Resources\ZabbixConnections\Pages;

use App\Filament\Admin\Resources\ZabbixConnections\ZabbixConnectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateZabbixConnection extends CreateRecord
{
    protected static string $resource = ZabbixConnectionResource::class;
}
