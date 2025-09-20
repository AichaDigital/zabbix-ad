<?php

namespace App\Filament\Admin\Resources\ZabbixTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ZabbixTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Template Name')
                    ->searchable()
                    ->sortable(),
                BadgeColumn::make('template_type')
                    ->colors([
                        'primary' => 'custom',
                        'success' => 'system',
                        'warning' => 'imported',
                    ])
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('triggers_count')
                    ->label('Triggers')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('history_retention')
                    ->label('History')
                    ->searchable(),
                TextColumn::make('trends_retention')
                    ->label('Trends')
                    ->searchable(),
                IconColumn::make('is_optimized')
                    ->label('Optimized')
                    ->boolean(),
                TextColumn::make('connection.name')
                    ->label('Connection')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('template_id')
                    ->label('Template ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_sync')
                    ->label('Last Sync')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
