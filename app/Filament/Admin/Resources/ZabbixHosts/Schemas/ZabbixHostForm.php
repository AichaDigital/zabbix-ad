<?php

namespace App\Filament\Admin\Resources\ZabbixHosts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ZabbixHostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('zabbix_connection_id')
                    ->required()
                    ->numeric(),
                TextInput::make('host_id')
                    ->required(),
                TextInput::make('host_name')
                    ->required(),
                TextInput::make('visible_name'),
                TextInput::make('ip_address'),
                TextInput::make('status')
                    ->required()
                    ->default('enabled'),
                TextInput::make('available')
                    ->required()
                    ->default('unknown'),
                TextInput::make('templates_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('items_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('last_check'),
                DateTimePicker::make('last_sync'),
            ]);
    }
}
