<?php

namespace App\Filament\Admin\Resources\Groups\Schemas;

use App\Modules\Institution\Models\SchoolGrade;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('school_grade_id')
                ->label('Grado')
                ->options(fn () => SchoolGrade::query()
                    ->where('is_active', true)
                    ->orderBy('level')
                    ->pluck('name', 'id'))
                ->required()
                ->searchable(),

            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),

            TextInput::make('year')
                ->label('Año lectivo')
                ->numeric()
                ->default(fn () => config('school.current_academic_year'))
                ->required(),
        ]);
    }
}
