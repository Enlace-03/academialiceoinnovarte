<?php

namespace App\Filament\Academic\Resources\Projects\Tables;

use App\Filament\Academic\Resources\Projects\ProjectResource;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Project\Models\Project;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Sin esto, el clic en una fila no navega a ningún lado -- el
            // fallback automático de Filament (ListRecords::table()) solo
            // busca una ACCIÓN de tabla llamada 'view'/'edit' vía
            // $table->getAction(...), no una página, y esta tabla nunca
            // registró recordActions(). Explícito hacia la página 'view'
            // (separación vista/edición, no directo a 'edit').
            ->recordUrl(fn (Project $record): string => ProjectResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(50),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Project::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),

                TextColumn::make('cycle.name')
                    ->label('Ciclo')
                    ->sortable(),

                TextColumn::make('semester')
                    ->label('Semestre')
                    ->formatStateUsing(fn (int $state): string => $state === 1 ? '1°' : '2°')
                    ->sortable(),

                TextColumn::make('year')
                    ->label('Año')
                    ->sortable(),

                TextColumn::make('createdBy.name')
                    ->label('Creado por')
                    ->searchable(),

                TextColumn::make('phases_count')
                    ->label('Fases')
                    ->counts('phases'),
            ])
            ->filters([
                SelectFilter::make('cycle_id')
                    ->label('Ciclo')
                    ->options(fn () => Cycle::query()->orderBy('order')->pluck('name', 'id')),

                SelectFilter::make('semester')
                    ->label('Semestre')
                    ->options([1 => '1°', 2 => '2°']),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Project::STATUSES),
            ])
            ->defaultSort('year', 'desc');
    }
}
