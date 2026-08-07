<?php

namespace App\Filament\Academic\Resources\Rubrics\Pages;

use App\Filament\Academic\Resources\Rubrics\RubricResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRubrics extends ListRecords
{
    protected static string $resource = RubricResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
