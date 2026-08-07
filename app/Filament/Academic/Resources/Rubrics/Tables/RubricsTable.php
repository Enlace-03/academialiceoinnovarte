<?php

namespace App\Filament\Academic\Resources\Rubrics\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RubricsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(60),

                TextColumn::make('criteria_count')
                    ->label('Criterios')
                    ->counts('criteria'),
            ])
            ->defaultSort('name');
    }
}
