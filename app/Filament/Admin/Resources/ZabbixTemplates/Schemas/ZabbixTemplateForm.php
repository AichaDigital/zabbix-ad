<?php

namespace App\Filament\Admin\Resources\ZabbixTemplates\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ZabbixTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('zabbix_connection_id')
                    ->required()
                    ->numeric(),
                TextInput::make('template_id')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('template_type')
                    ->required()
                    ->default('custom'),
                TextInput::make('items_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('triggers_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('history_retention')
                    ->required()
                    ->default('7d'),
                TextInput::make('trends_retention')
                    ->required()
                    ->default('30d'),
                Toggle::make('is_optimized')
                    ->required(),
                DateTimePicker::make('last_sync'),
            ]);
    }
}
