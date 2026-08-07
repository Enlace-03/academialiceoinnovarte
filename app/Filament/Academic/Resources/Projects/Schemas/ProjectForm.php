<?php

namespace App\Filament\Academic\Resources\Projects\Schemas;

use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\ThinkingField;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('cycle_id')
                ->label('Ciclo')
                ->options(fn () => Cycle::query()->orderBy('order')->pluck('name', 'id'))
                ->required()
                ->searchable(),

            TextInput::make('title')
                ->label('Título')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Select::make('semester')
                ->label('Semestre')
                ->options([1 => 'Primer semestre', 2 => 'Segundo semestre'])
                ->required(),

            TextInput::make('year')
                ->label('Año lectivo')
                ->numeric()
                ->default(fn () => config('school.current_academic_year'))
                ->required(),

            TextInput::make('suggested_duration_weeks')
                ->label('Duración sugerida (semanas)')
                ->numeric()
                ->minValue(1),

            CheckboxList::make('thinkingFields')
                ->label('Campos de pensamiento')
                ->relationship('thinkingFields', 'name')
                ->options(fn () => ThinkingField::query()->orderBy('order')->pluck('name', 'id'))
                ->columns(2)
                ->columnSpanFull(),

            Textarea::make('problem_situation')
                ->label('Situación problema')
                ->rows(4)
                ->columnSpanFull(),

            Textarea::make('guiding_question')
                ->label('Pregunta guía')
                ->rows(2)
                ->columnSpanFull(),

            Textarea::make('purpose')
                ->label('Propósito')
                ->rows(3)
                ->columnSpanFull(),

            Textarea::make('expected_impact')
                ->label('Impacto esperado')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }
}
