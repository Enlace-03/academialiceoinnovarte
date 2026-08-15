<?php

namespace App\Filament\Academic\Resources\Students\Pages;

use App\Filament\Academic\Resources\Students\StudentResource;
use Filament\Resources\Pages\ListRecords;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;
}
