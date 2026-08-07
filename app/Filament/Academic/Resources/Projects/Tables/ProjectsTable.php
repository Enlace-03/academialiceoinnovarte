<?php

namespace App\Filament\Academic\Resources\Projects\Tables;

use App\Modules\Institution\Models\Cycle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(50),

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
            ])
            ->defaultSort('year', 'desc');
    }
}
