<?php

namespace App\Filament\Admin\Resources\BackgroundJobs\Pages;

use App\Filament\Admin\Resources\BackgroundJobs\BackgroundJobResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBackgroundJob extends CreateRecord
{
    protected static string $resource = BackgroundJobResource::class;
}
