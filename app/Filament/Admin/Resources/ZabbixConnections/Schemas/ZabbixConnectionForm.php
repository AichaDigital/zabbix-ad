<?php

namespace App\Filament\Admin\Resources\ZabbixConnections\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ZabbixConnectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('url')
                    ->url()
                    ->required(),
                TextInput::make('environment')
                    ->required()
                    ->default('production'),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('max_requests_per_minute')
                    ->required()
                    ->numeric()
                    ->default(60),
                TextInput::make('timeout_seconds')
                    ->required()
                    ->numeric()
                    ->default(30),
                DateTimePicker::make('last_connection_test'),
                TextInput::make('connection_status')
                    ->required()
                    ->default('active'),
            ]);
    }
}
