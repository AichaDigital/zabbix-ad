<?php

namespace App\Filament\Admin\Resources\AuditLogs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AuditLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Select::make('zabbix_connection_id')
                    ->relationship('zabbixConnection', 'name'),
                TextInput::make('action')
                    ->required(),
                TextInput::make('resource_type')
                    ->required(),
                TextInput::make('resource_id'),
                Textarea::make('old_values')
                    ->columnSpanFull(),
                Textarea::make('new_values')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required(),
                Textarea::make('error_message')
                    ->columnSpanFull(),
                TextInput::make('execution_time_ms')
                    ->numeric(),
                TextInput::make('ip_address'),
                Textarea::make('user_agent')
                    ->columnSpanFull(),
            ]);
    }
}
