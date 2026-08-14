<?php

namespace App\Filament\Academic\Resources\Projects\Schemas;

use App\Modules\Project\Models\Project;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Columna de contexto de solo lectura para ViewProject -- situación
 * problema, pregunta guía, propósito, impacto esperado, ciclo, semestre,
 * campos de pensamiento. Mismo criterio de "referencia, no editable" que
 * <x-rubric-criteria-table> del lado del estudiante (Hito 3b-3), pero acá
 * con los componentes nativos de Filament (Section + TextEntry) -- esta
 * pantalla vive dentro del panel /academia, no del portal.
 */
class ProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos generales')
                ->columns(2)
                ->schema([
                    TextEntry::make('cycle.name')
                        ->label('Ciclo'),

                    TextEntry::make('semester')
                        ->label('Semestre')
                        ->formatStateUsing(fn (int $state): string => $state === 1 ? '1° semestre' : '2° semestre'),

                    TextEntry::make('year')
                        ->label('Año lectivo'),

                    TextEntry::make('suggested_duration_weeks')
                        ->label('Duración sugerida')
                        ->formatStateUsing(fn (?int $state): string => $state !== null ? "{$state} semanas" : '—'),

                    TextEntry::make('thinkingFields')
                        ->label('Campos de pensamiento')
                        ->state(fn (Project $record): string => $record->thinkingFields->pluck('name')->implode(', ') ?: 'Sin campos asignados')
                        ->columnSpanFull(),
                ]),

            Section::make('Contexto del proyecto')
                ->schema([
                    TextEntry::make('problem_situation')
                        ->label('Situación problema')
                        ->placeholder('—')
                        ->columnSpanFull(),

                    TextEntry::make('guiding_question')
                        ->label('Pregunta guía')
                        ->placeholder('—')
                        ->columnSpanFull(),

                    TextEntry::make('purpose')
                        ->label('Propósito')
                        ->placeholder('—')
                        ->columnSpanFull(),

                    TextEntry::make('expected_impact')
                        ->label('Impacto esperado')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
