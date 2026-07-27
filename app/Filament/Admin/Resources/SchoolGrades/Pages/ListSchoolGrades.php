<?php

namespace App\Filament\Admin\Resources\SchoolGrades\Pages;

use App\Filament\Admin\Resources\SchoolGrades\SchoolGradeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSchoolGrades extends ListRecords
{
    protected static string $resource = SchoolGradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
