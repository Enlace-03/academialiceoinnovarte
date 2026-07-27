<?php

namespace App\Filament\Admin\Resources\ThinkingFields\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ThinkingFieldsTable
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

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),

                TextColumn::make('purpose')
                    ->label('Propósito')
                    ->limit(60)
                    ->wrap(),
            ])
            ->defaultSort('order');
    }
}
