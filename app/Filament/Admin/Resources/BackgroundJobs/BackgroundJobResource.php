<?php

namespace App\Filament\Admin\Resources\BackgroundJobs;

use App\Filament\Admin\Resources\BackgroundJobs\Pages\CreateBackgroundJob;
use App\Filament\Admin\Resources\BackgroundJobs\Pages\EditBackgroundJob;
use App\Filament\Admin\Resources\BackgroundJobs\Pages\ListBackgroundJobs;
use App\Filament\Admin\Resources\BackgroundJobs\Schemas\BackgroundJobForm;
use App\Filament\Admin\Resources\BackgroundJobs\Tables\BackgroundJobsTable;
use App\Models\BackgroundJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BackgroundJobResource extends Resource
{
    protected static ?string $model = BackgroundJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return BackgroundJobForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BackgroundJobsTable::configure($table);
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
            'index' => ListBackgroundJobs::route('/'),
            'create' => CreateBackgroundJob::route('/create'),
            'edit' => EditBackgroundJob::route('/{record}/edit'),
        ];
    }
}
