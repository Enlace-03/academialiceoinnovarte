<?php

namespace App\Filament\Admin\Resources\Groups\Tables;

use App\Modules\Institution\Models\Cycle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cycle.name')
                    ->label('Ciclo')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                TextColumn::make('year')
                    ->label('Año lectivo')
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label('Estudiantes')
                    ->counts('users'),
            ])
            ->filters([
                SelectFilter::make('cycle_id')
                    ->label('Ciclo')
                    ->options(fn () => Cycle::query()->orderBy('order')->pluck('name', 'id')),
            ])
            ->defaultSort('year', 'desc');
    }
}
