<?php

namespace App\Filament\Admin\Resources\SchoolGrades\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SchoolGradesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('level')
                    ->label('Nivel')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                TextColumn::make('cycle.name')
                    ->label('Ciclo')
                    ->badge(),

                TextColumn::make('groups_count')
                    ->label('Grupos')
                    ->counts('groups'),

                ToggleColumn::make('is_active')
                    ->label('Activo'),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
            ])
            ->defaultSort('level');
    }
}
