<?php

namespace App\Filament\Admin\Resources\TemplateOptimizationRules\Pages;

use App\Filament\Admin\Resources\TemplateOptimizationRules\TemplateOptimizationRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTemplateOptimizationRule extends EditRecord
{
    protected static string $resource = TemplateOptimizationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
