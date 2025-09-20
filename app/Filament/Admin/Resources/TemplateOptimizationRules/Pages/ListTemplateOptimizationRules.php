<?php

namespace App\Filament\Admin\Resources\TemplateOptimizationRules\Pages;

use App\Filament\Admin\Resources\TemplateOptimizationRules\TemplateOptimizationRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTemplateOptimizationRules extends ListRecords
{
    protected static string $resource = TemplateOptimizationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
