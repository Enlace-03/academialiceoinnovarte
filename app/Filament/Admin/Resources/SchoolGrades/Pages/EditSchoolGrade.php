<?php

namespace App\Filament\Admin\Resources\SchoolGrades\Pages;

use App\Filament\Admin\Resources\SchoolGrades\SchoolGradeResource;
use Filament\Resources\Pages\EditRecord;

// Sin DeleteAction a propósito: un grado escolar nunca se borra (regla de
// negocio del Hito 1A). Se desactiva con el ToggleColumn de la tabla, lo que
// preserva su historial y a los estudiantes ya matriculados en él.
class EditSchoolGrade extends EditRecord
{
    protected static string $resource = SchoolGradeResource::class;
}
