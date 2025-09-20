<?php

namespace App\Filament\Admin\Resources\TemplateOptimizationRules\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TemplateOptimizationRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('environment')
                    ->required()
                    ->default('all'),
                TextInput::make('template_pattern'),
                TextInput::make('history_from'),
                TextInput::make('history_to'),
                TextInput::make('trends_from'),
                TextInput::make('trends_to'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
