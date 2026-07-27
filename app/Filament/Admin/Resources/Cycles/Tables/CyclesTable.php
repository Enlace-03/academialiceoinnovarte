<?php

namespace App\Filament\Admin\Resources\Cycles\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CyclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order')
                    ->label('Orden')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(60)
                    ->wrap(),

                TextColumn::make('school_grades_count')
                    ->label('Grados')
                    ->counts('schoolGrades'),
            ])
            ->defaultSort('order');
    }
}
