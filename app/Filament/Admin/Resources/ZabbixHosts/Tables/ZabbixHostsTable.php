<?php

namespace App\Filament\Admin\Resources\ZabbixHosts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ZabbixHostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('host_name')
                    ->label('Host Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('visible_name')
                    ->label('Visible Name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable(),
                BadgeColumn::make('status')
                    ->colors([
                        'success' => 'enabled',
                        'danger' => 'disabled',
                        'warning' => 'maintenance',
                    ])
                    ->sortable(),
                BadgeColumn::make('available')
                    ->colors([
                        'success' => 'available',
                        'danger' => 'unavailable',
                        'warning' => 'unknown',
                    ])
                    ->sortable(),
                TextColumn::make('templates_count')
                    ->label('Templates')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_check')
                    ->label('Last Check')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('connection.name')
                    ->label('Connection')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('host_id')
                    ->label('Host ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_sync')
                    ->label('Last Sync')
                    ->dateTime()
                    ->sortable()
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
