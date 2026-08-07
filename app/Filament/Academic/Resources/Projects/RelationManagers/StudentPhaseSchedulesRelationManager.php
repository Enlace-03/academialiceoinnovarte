<?php

namespace App\Filament\Academic\Resources\Projects\RelationManagers;

use App\Modules\Project\Actions\UpdateStudentPhaseScheduleAction;
use App\Modules\Project\Models\StudentPhaseSchedule;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * Relation Manager simple (listar/editar) para el Hito 1C. La experiencia
 * pulida del docente para esto se construye cuando exista el panel de
 * seguimiento — aquí solo se permite editar fechas, siempre a través de
 * UpdateStudentPhaseScheduleAction (extension_count + propagación a equipo).
 */
class StudentPhaseSchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'studentPhaseSchedules';

    protected static ?string $title = 'Cronograma de estudiantes';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.name')
                    ->label('Estudiante')
                    ->searchable(),

                TextColumn::make('phase.name')
                    ->label('Fase'),

                TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date(),

                TextColumn::make('end_date')
                    ->label('Fin')
                    ->date(),

                TextColumn::make('extension_count')
                    ->label('Extensiones')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 2 => 'danger',
                        $state === 1 => 'warning',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->schema([
                        DatePicker::make('start_date')->label('Inicio')->required(),
                        DatePicker::make('end_date')->label('Fin')->required(),
                    ])
                    ->using(function (StudentPhaseSchedule $record, array $data): StudentPhaseSchedule {
                        return app(UpdateStudentPhaseScheduleAction::class)->execute(
                            $record->student,
                            $record->phase,
                            $data['start_date'],
                            $data['end_date'],
                        );
                    }),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('start_date')->label('Inicio')->required(),
            DatePicker::make('end_date')->label('Fin')->required(),
        ]);
    }
}
