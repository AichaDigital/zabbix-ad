<?php

namespace App\Filament\Admin\Resources\ZabbixTemplates;

use App\Filament\Admin\Resources\ZabbixTemplates\Pages\CreateZabbixTemplate;
use App\Filament\Admin\Resources\ZabbixTemplates\Pages\EditZabbixTemplate;
use App\Filament\Admin\Resources\ZabbixTemplates\Pages\ListZabbixTemplates;
use App\Filament\Admin\Resources\ZabbixTemplates\Schemas\ZabbixTemplateForm;
use App\Filament\Admin\Resources\ZabbixTemplates\Tables\ZabbixTemplatesTable;
use App\Models\ZabbixTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ZabbixTemplateResource extends Resource
{
    protected static ?string $model = ZabbixTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ZabbixTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ZabbixTemplatesTable::configure($table);
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
            'index' => ListZabbixTemplates::route('/'),
            'create' => CreateZabbixTemplate::route('/create'),
            'edit' => EditZabbixTemplate::route('/{record}/edit'),
        ];
    }
}
