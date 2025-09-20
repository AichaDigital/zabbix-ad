<?php

namespace App\Filament\Admin\Resources\ZabbixHosts;

use App\Filament\Admin\Resources\ZabbixHosts\Pages\CreateZabbixHost;
use App\Filament\Admin\Resources\ZabbixHosts\Pages\EditZabbixHost;
use App\Filament\Admin\Resources\ZabbixHosts\Pages\ListZabbixHosts;
use App\Filament\Admin\Resources\ZabbixHosts\Schemas\ZabbixHostForm;
use App\Filament\Admin\Resources\ZabbixHosts\Tables\ZabbixHostsTable;
use App\Models\ZabbixHost;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ZabbixHostResource extends Resource
{
    protected static ?string $model = ZabbixHost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ZabbixHostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ZabbixHostsTable::configure($table);
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
            'index' => ListZabbixHosts::route('/'),
            'create' => CreateZabbixHost::route('/create'),
            'edit' => EditZabbixHost::route('/{record}/edit'),
        ];
    }
}
