<?php

namespace App\Filament\Admin\Resources\Groups\Tables;

use App\Modules\Institution\Models\SchoolGrade;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('schoolGrade.name')
                    ->label('Grado')
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
                SelectFilter::make('school_grade_id')
                    ->label('Grado')
                    ->options(fn () => SchoolGrade::query()->orderBy('level')->pluck('name', 'id')),
            ])
            ->defaultSort('year', 'desc');
    }
}
