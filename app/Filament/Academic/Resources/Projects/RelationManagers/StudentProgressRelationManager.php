<?php

namespace App\Filament\Academic\Resources\Projects\RelationManagers;

use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Tracking\Models\PerformanceSnapshot;
use App\Modules\Tracking\Models\StudentProgress;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Vista de solo lectura por estudiante (Hito 4, decisión confirmada) --
 * sin CreateAction/EditAction/DeleteAction a propósito: estas filas las
 * escribe únicamente RecalculateStudentProgressAction, nunca el personal a
 * mano. El dashboard agregado por campo de pensamiento queda fuera de este
 * hito (Hito 4b, Analytics).
 *
 * Dos columnas separadas para avance (Avance %) y nivel cualitativo
 * (Nivel), nunca fusionadas -- mismo criterio que la barra del portal de
 * estudiante.
 */
class StudentProgressRelationManager extends RelationManager
{
    protected static string $relationship = 'studentProgress';

    protected static ?string $title = 'Seguimiento por estudiante';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('student.name')
                    ->label('Estudiante')
                    ->sortable(),

                TextColumn::make('progress_pct')
                    ->label('Avance')
                    ->formatStateUsing(fn (int $state): string => "{$state}%")
                    ->sortable(),

                TextColumn::make('qualitative_level')
                    ->label('Nivel')
                    ->badge()
                    ->state(fn (StudentProgress $record) => $this->qualitativeLevel($record))
                    ->formatStateUsing(fn (?RubricLevel $state): string => $state?->label ?? 'Sin evaluar')
                    ->color(fn (?RubricLevel $state): string => match ($state?->key) {
                        'inicio' => 'danger',
                        'en_proceso' => 'warning',
                        'logro_esperado' => 'warning',
                        'logro_destacado' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('evidences_submitted')
                    ->label('Evidencias')
                    ->formatStateUsing(fn (StudentProgress $record): string => "{$record->evidences_submitted}/{$record->evidences_total}"),

                TextColumn::make('forum_participations')
                    ->label('Foro'),

                TextColumn::make('chat_participations')
                    ->label('Chat'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'not_started' => 'Sin comenzar',
                        'in_progress' => 'En curso',
                        'completed' => 'Completado',
                        default => $state,
                    }),

                TextColumn::make('updated_at')
                    ->label('Último recálculo')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('progress_pct', 'desc')
            ->emptyStateHeading('Todavía no hay avance registrado')
            ->emptyStateDescription('Se llena automáticamente cuando los estudiantes entregan, participan en el foro o en el chat.')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    protected function qualitativeLevel(StudentProgress $record): ?RubricLevel
    {
        $snapshot = PerformanceSnapshot::query()
            ->where('student_id', $record->student_id)
            ->where('project_id', $record->project_id)
            ->latest('snapshot_date')
            ->first();

        $levelKey = $snapshot?->metrics['qualitative_level_key'] ?? null;

        return $levelKey !== null ? RubricLevel::where('key', $levelKey)->first() : null;
    }
}
