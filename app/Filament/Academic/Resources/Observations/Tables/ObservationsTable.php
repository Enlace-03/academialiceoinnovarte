<?php

namespace App\Filament\Academic\Resources\Observations\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ObservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.name')
                    ->label('Estudiante')
                    ->searchable(),

                TextColumn::make('teacher.name')
                    ->label('Docente')
                    ->searchable(),

                TextColumn::make('project.title')
                    ->label('Proyecto')
                    ->placeholder('—'),

                TextColumn::make('content')
                    ->label('Observación')
                    ->limit(50),

                IconColumn::make('visible_to_parents')
                    ->label('Visible al acudiente')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
