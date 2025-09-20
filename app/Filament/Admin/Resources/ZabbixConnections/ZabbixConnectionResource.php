<?php

namespace App\Filament\Admin\Resources\ZabbixConnections;

use App\Filament\Admin\Resources\ZabbixConnections\Pages\CreateZabbixConnection;
use App\Filament\Admin\Resources\ZabbixConnections\Pages\EditZabbixConnection;
use App\Filament\Admin\Resources\ZabbixConnections\Pages\ListZabbixConnections;
use App\Filament\Admin\Resources\ZabbixConnections\Schemas\ZabbixConnectionForm;
use App\Filament\Admin\Resources\ZabbixConnections\Tables\ZabbixConnectionsTable;
use App\Models\ZabbixConnection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ZabbixConnectionResource extends Resource
{
    protected static ?string $model = ZabbixConnection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ZabbixConnectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ZabbixConnectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListZabbixConnections::route('/'),
            'create' => CreateZabbixConnection::route('/create'),
            'edit' => EditZabbixConnection::route('/{record}/edit'),
        ];
    }
}
