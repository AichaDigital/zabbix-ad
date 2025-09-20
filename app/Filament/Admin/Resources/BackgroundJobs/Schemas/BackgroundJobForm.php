<?php

namespace App\Filament\Admin\Resources\BackgroundJobs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BackgroundJobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('job_type')
                    ->required(),
                Select::make('zabbix_connection_id')
                    ->relationship('zabbixConnection', 'name'),
                Textarea::make('parameters')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                TextInput::make('progress_percentage')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('started_at'),
                DateTimePicker::make('completed_at'),
                Textarea::make('error_message')
                    ->columnSpanFull(),
                Textarea::make('result_data')
                    ->columnSpanFull(),
            ]);
    }
}
