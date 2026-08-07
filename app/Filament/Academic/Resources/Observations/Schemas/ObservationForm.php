<?php

namespace App\Filament\Academic\Resources\Observations\Schemas;

use App\Models\User;
use App\Modules\Project\Models\Project;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ObservationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('student_id')
                ->label('Estudiante')
                ->options(fn () => User::query()->role('student')->pluck('name', 'id'))
                ->required()
                ->searchable(),

            Select::make('project_id')
                ->label('Proyecto (opcional)')
                ->options(fn () => Project::query()->pluck('title', 'id'))
                ->searchable(),

            Textarea::make('content')
                ->label('Observación')
                ->required()
                ->rows(4)
                ->columnSpanFull(),

            Toggle::make('visible_to_parents')
                ->label('Visible para el acudiente')
                ->default(true),
        ]);
    }
}
