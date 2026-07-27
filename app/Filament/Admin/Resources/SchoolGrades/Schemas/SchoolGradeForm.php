<?php

namespace App\Filament\Admin\Resources\SchoolGrades\Schemas;

use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Institution;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SchoolGradeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('institution_id')
                ->default(fn () => Institution::query()->value('id')),

            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),

            TextInput::make('level')
                ->label('Nivel (0-9)')
                ->numeric()
                ->minValue(0)
                ->maxValue(9)
                ->required(),

            Select::make('cycle_id')
                ->label('Ciclo')
                ->options(fn () => Cycle::query()->orderBy('order')->pluck('name', 'id'))
                ->required(),

            Toggle::make('is_active')
                ->label('Grado activo')
                ->helperText('Actívalo o desactívalo para incluirlo o sacarlo de los selectores de nueva matrícula. No afecta a estudiantes ya matriculados en este grado.')
                ->default(true)
                ->inline(false),
        ]);
    }
}
