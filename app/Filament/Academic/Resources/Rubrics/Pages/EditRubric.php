<?php

namespace App\Filament\Academic\Resources\Rubrics\Pages;

use App\Filament\Academic\Resources\Rubrics\RubricResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRubric extends EditRecord
{
    protected static string $resource = RubricResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
