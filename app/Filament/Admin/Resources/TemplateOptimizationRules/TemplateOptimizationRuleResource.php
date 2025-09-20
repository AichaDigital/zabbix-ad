<?php

namespace App\Filament\Admin\Resources\TemplateOptimizationRules;

use App\Filament\Admin\Resources\TemplateOptimizationRules\Pages\CreateTemplateOptimizationRule;
use App\Filament\Admin\Resources\TemplateOptimizationRules\Pages\EditTemplateOptimizationRule;
use App\Filament\Admin\Resources\TemplateOptimizationRules\Pages\ListTemplateOptimizationRules;
use App\Filament\Admin\Resources\TemplateOptimizationRules\Schemas\TemplateOptimizationRuleForm;
use App\Filament\Admin\Resources\TemplateOptimizationRules\Tables\TemplateOptimizationRulesTable;
use App\Models\TemplateOptimizationRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TemplateOptimizationRuleResource extends Resource
{
    protected static ?string $model = TemplateOptimizationRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return TemplateOptimizationRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TemplateOptimizationRulesTable::configure($table);
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
            'index' => ListTemplateOptimizationRules::route('/'),
            'create' => CreateTemplateOptimizationRule::route('/create'),
            'edit' => EditTemplateOptimizationRule::route('/{record}/edit'),
        ];
    }
}
